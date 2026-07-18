/**
 * Editor UI for the Bitcoin Payment Button block.
 *
 * The frontend is server-rendered (render.php), so this component is only the
 * authoring experience: InspectorControls for every attribute plus a live
 * preview that mirrors what PHP will output. The preview builds the same BIP21
 * URI via the shared src/lib/bip21.ts so what you see matches the frontend.
 */
import type { BlockEditProps } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	SelectControl,
	ToggleControl,
	Notice,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';

import { addrLooksValid, buildURI, formatBtc } from './lib/bip21';

type AmountMode = 'none' | 'btc' | 'fiat';
type Theme = 'auto' | 'light' | 'dark';
type Align = 'left' | 'center' | 'right';

export interface Attributes {
	address: string;
	amountMode: AmountMode;
	amountBtc: string;
	amountFiat: string;
	currency: string;
	label: string;
	message: string;
	buttonText: string;
	buttonAlign: Align;
	theme: Theme;
	showQr: boolean;
	showPowered: boolean;
	pickerUnit: 'fiat' | 'btc';
	presetsEnabled: boolean;
	presetValues: string;
	rangeEnabled: boolean;
	rangeMin: string;
	rangeMax: string;
	freeEnabled: boolean;
	// Block attributes are an open string-keyed bag; the index signature lets
	// this satisfy registerBlockType's `Record<string, unknown>` constraint.
	[ key: string ]: unknown;
}

/**
 * Localised fiat formatting for the editor preview (mirrors view.ts).
 * @param value
 * @param currency
 */
function fmtFiat( value: number, currency: string ): string {
	try {
		return new Intl.NumberFormat( undefined, {
			style: 'currency',
			currency,
			minimumFractionDigits: 0,
			maximumFractionDigits:
				currency === 'JPY' || value >= 100 || Number.isInteger( value )
					? 0
					: 2,
		} ).format( value );
	} catch ( e ) {
		return `${ value } ${ currency }`;
	}
}

const CURRENCIES: string[] = [ 'USD', 'EUR', 'GBP', 'JPY', 'CAD' ];
const RATES_URL = 'https://api.chainkit.dev/v1/public/btc/rates';

export default function Edit( {
	attributes,
	setAttributes,
}: BlockEditProps< Attributes > ) {
	const {
		address,
		amountMode,
		amountBtc,
		amountFiat,
		currency,
		label,
		message,
		buttonText,
		buttonAlign,
		theme,
		showQr,
		showPowered,
		pickerUnit,
		presetsEnabled,
		presetValues,
		rangeEnabled,
		rangeMin,
		rangeMax,
		freeEnabled,
	} = attributes;

	const [ rates, setRates ] = useState< Record< string, number > | null >(
		null
	);
	const [ rateError, setRateError ] = useState( false );

	// Fetch rates once, only to power the editor preview of fiat mode. The real
	// conversion at publish time happens server-side in PHP.
	useEffect( () => {
		if ( amountMode !== 'fiat' || rates ) {
			return;
		}
		let alive = true;
		fetch( RATES_URL, { headers: { Accept: 'application/json' } } )
			.then( ( r ) => ( r.ok ? r.json() : Promise.reject( r.status ) ) )
			.then(
				( body: {
					rates?: Array< { currency?: string; rate?: string } >;
				} ) => {
					if ( ! alive ) {
						return;
					}
					const map: Record< string, number > = {};
					( body?.rates || [] ).forEach( ( row ) => {
						if ( row?.currency && row?.rate ) {
							map[ String( row.currency ).toUpperCase() ] =
								parseFloat( row.rate );
						}
					} );
					setRates( map );
				}
			)
			.catch( () => alive && setRateError( true ) );
		return () => {
			alive = false;
		};
	}, [ amountMode, rates ] );

	const addr = ( address || '' ).trim();
	const addrValid = addrLooksValid( addr );

	// Resolve the preview headline + secondary line, matching the frontend:
	// fiat leads in fiat mode, BTC leads in BTC mode, "Any amount" for the picker.
	let btcAmount: number | null = null;
	let heroText = '';
	let subText = '';
	let isApprox = false;
	if ( amountMode === 'btc' ) {
		const v = parseFloat( amountBtc );
		if ( isFinite( v ) && v > 0 ) {
			btcAmount = v;
			heroText = `${ formatBtc( v ) } BTC`;
			const rate = rates?.[ currency ];
			if ( rate && rate > 0 ) {
				subText = `≈ ${ fmtFiat( v * rate, currency ) }`;
			}
		}
	} else if ( amountMode === 'fiat' ) {
		const f = parseFloat( amountFiat );
		if ( isFinite( f ) && f > 0 ) {
			heroText = fmtFiat( f, currency );
			const rate = rates?.[ currency ];
			if ( rate && rate > 0 ) {
				btcAmount = f / rate;
				subText = `≈ ${ formatBtc( btcAmount ) } BTC`;
				isApprox = true;
			}
		}
	} else {
		heroText = __( 'Any amount', 'chainkit-bitcoin-payment-button' );
	}

	// Preview chips for the picker (payer-decides mode).
	const presetChips =
		amountMode === 'none' && presetsEnabled
			? presetValues
					.split( ',' )
					.map( ( s ) => parseFloat( s.trim() ) )
					.filter( ( n ) => isFinite( n ) && n > 0 )
					.map( ( n ) =>
						pickerUnit === 'btc'
							? `${ formatBtc( n ) } BTC`
							: fmtFiat( n, currency )
					)
			: [];

	const uri = buildURI( addr, btcAmount, label, message );

	const blockProps = useBlockProps( {
		className: `chainkit-bpb-editor chainkit-bpb--theme-${ theme } chainkit-bpb--align-${ buttonAlign }`,
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Payment', 'chainkit-bitcoin-payment-button' ) }
				>
					<TextControl
						label={ __(
							'Bitcoin address',
							'chainkit-bitcoin-payment-button'
						) }
						value={ address }
						onChange={ ( v ) => setAttributes( { address: v } ) }
						placeholder="bc1q… · 1… · 3…"
						spellCheck={ false }
						autoComplete="off"
						__nextHasNoMarginBottom
					/>
					{ addr !== '' && ! addrValid && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'That does not look like a Bitcoin address. The button will not render until it does.',
								'chainkit-bitcoin-payment-button'
							) }
						</Notice>
					) }

					<SelectControl
						label={ __(
							'Amount',
							'chainkit-bitcoin-payment-button'
						) }
						value={ amountMode }
						options={ [
							{
								label: __(
									'Payer decides',
									'chainkit-bitcoin-payment-button'
								),
								value: 'none',
							},
							{
								label: __(
									'Fixed BTC',
									'chainkit-bitcoin-payment-button'
								),
								value: 'btc',
							},
							{
								label: __(
									'Fiat (live rate)',
									'chainkit-bitcoin-payment-button'
								),
								value: 'fiat',
							},
						] }
						onChange={ ( v ) =>
							setAttributes( { amountMode: v as AmountMode } )
						}
						__nextHasNoMarginBottom
					/>

					{ amountMode === 'btc' && (
						<TextControl
							label={ __(
								'Amount (BTC)',
								'chainkit-bitcoin-payment-button'
							) }
							type="number"
							min="0"
							step="0.00000001"
							value={ amountBtc }
							onChange={ ( v ) =>
								setAttributes( { amountBtc: v } )
							}
							placeholder="0.00000000"
							__nextHasNoMarginBottom
						/>
					) }

					{ amountMode === 'fiat' && (
						<>
							<TextControl
								label={ __(
									'Amount (fiat)',
									'chainkit-bitcoin-payment-button'
								) }
								type="number"
								min="0"
								step="0.01"
								value={ amountFiat }
								onChange={ ( v ) =>
									setAttributes( { amountFiat: v } )
								}
								placeholder="0.00"
								__nextHasNoMarginBottom
							/>
							<SelectControl
								label={ __(
									'Currency',
									'chainkit-bitcoin-payment-button'
								) }
								value={ currency }
								options={ CURRENCIES.map( ( c ) => ( {
									label: c,
									value: c,
								} ) ) }
								onChange={ ( v ) =>
									setAttributes( { currency: v } )
								}
								__nextHasNoMarginBottom
							/>
							{ rateError && (
								<Notice
									status="warning"
									isDismissible={ false }
								>
									{ __(
										'Live rates are unavailable in the editor. The button will still convert server-side when the page loads, or fall back to an address-only request.',
										'chainkit-bitcoin-payment-button'
									) }
								</Notice>
							) }
						</>
					) }

					{ amountMode === 'none' && (
						<>
							<SelectControl
								label={ __(
									'Amounts in',
									'chainkit-bitcoin-payment-button'
								) }
								value={ pickerUnit }
								options={ [
									{
										label: `${ currency } (fiat)`,
										value: 'fiat',
									},
									{ label: 'BTC', value: 'btc' },
								] }
								onChange={ ( v ) =>
									setAttributes( {
										pickerUnit: v as 'fiat' | 'btc',
									} )
								}
								help={ __(
									'The unit for the amounts a payer chooses.',
									'chainkit-bitcoin-payment-button'
								) }
								__nextHasNoMarginBottom
							/>
							<ToggleControl
								label={ __(
									'Preset amounts',
									'chainkit-bitcoin-payment-button'
								) }
								checked={ presetsEnabled }
								onChange={ ( v ) =>
									setAttributes( { presetsEnabled: v } )
								}
								__nextHasNoMarginBottom
							/>
							{ presetsEnabled && (
								<TextControl
									label={ __(
										'Preset values (comma-separated)',
										'chainkit-bitcoin-payment-button'
									) }
									value={ presetValues }
									onChange={ ( v ) =>
										setAttributes( { presetValues: v } )
									}
									placeholder="1, 2, 5, 10"
									__nextHasNoMarginBottom
								/>
							) }
							<ToggleControl
								label={ __(
									'Slider (range)',
									'chainkit-bitcoin-payment-button'
								) }
								checked={ rangeEnabled }
								onChange={ ( v ) =>
									setAttributes( { rangeEnabled: v } )
								}
								__nextHasNoMarginBottom
							/>
							{ rangeEnabled && (
								<>
									<TextControl
										label={ __(
											'Minimum',
											'chainkit-bitcoin-payment-button'
										) }
										type="number"
										min="0"
										value={ rangeMin }
										onChange={ ( v ) =>
											setAttributes( { rangeMin: v } )
										}
										__nextHasNoMarginBottom
									/>
									<TextControl
										label={ __(
											'Maximum',
											'chainkit-bitcoin-payment-button'
										) }
										type="number"
										min="0"
										value={ rangeMax }
										onChange={ ( v ) =>
											setAttributes( { rangeMax: v } )
										}
										__nextHasNoMarginBottom
									/>
								</>
							) }
							<ToggleControl
								label={ __(
									'Custom amount field',
									'chainkit-bitcoin-payment-button'
								) }
								checked={ freeEnabled }
								onChange={ ( v ) =>
									setAttributes( { freeEnabled: v } )
								}
								__nextHasNoMarginBottom
							/>
						</>
					) }
				</PanelBody>

				<PanelBody
					title={ __( 'Details', 'chainkit-bitcoin-payment-button' ) }
					initialOpen={ false }
				>
					<TextControl
						label={ __(
							'Label (wallet)',
							'chainkit-bitcoin-payment-button'
						) }
						value={ label }
						onChange={ ( v ) => setAttributes( { label: v } ) }
						placeholder={ __(
							'Your store name',
							'chainkit-bitcoin-payment-button'
						) }
						maxLength={ 80 }
						__nextHasNoMarginBottom
					/>
					<TextareaControl
						label={ __(
							'Message (wallet)',
							'chainkit-bitcoin-payment-button'
						) }
						value={ message }
						onChange={ ( v ) => setAttributes( { message: v } ) }
						placeholder={ __(
							'Order #1042',
							'chainkit-bitcoin-payment-button'
						) }
						maxLength={ 120 }
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				<PanelBody
					title={ __(
						'Appearance',
						'chainkit-bitcoin-payment-button'
					) }
					initialOpen={ false }
				>
					<TextControl
						label={ __(
							'Button text',
							'chainkit-bitcoin-payment-button'
						) }
						value={ buttonText }
						onChange={ ( v ) => setAttributes( { buttonText: v } ) }
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __(
							'Alignment',
							'chainkit-bitcoin-payment-button'
						) }
						value={ buttonAlign }
						options={ [
							{
								label: __(
									'Left',
									'chainkit-bitcoin-payment-button'
								),
								value: 'left',
							},
							{
								label: __(
									'Center',
									'chainkit-bitcoin-payment-button'
								),
								value: 'center',
							},
							{
								label: __(
									'Right',
									'chainkit-bitcoin-payment-button'
								),
								value: 'right',
							},
						] }
						onChange={ ( v ) =>
							setAttributes( { buttonAlign: v as Align } )
						}
						__nextHasNoMarginBottom
					/>
					<SelectControl
						label={ __(
							'Theme',
							'chainkit-bitcoin-payment-button'
						) }
						value={ theme }
						options={ [
							{
								label: __(
									'Auto',
									'chainkit-bitcoin-payment-button'
								),
								value: 'auto',
							},
							{
								label: __(
									'Light',
									'chainkit-bitcoin-payment-button'
								),
								value: 'light',
							},
							{
								label: __(
									'Dark',
									'chainkit-bitcoin-payment-button'
								),
								value: 'dark',
							},
						] }
						onChange={ ( v ) =>
							setAttributes( { theme: v as Theme } )
						}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __(
							'Show QR code',
							'chainkit-bitcoin-payment-button'
						) }
						checked={ showQr }
						onChange={ ( v ) => setAttributes( { showQr: v } ) }
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __(
							'Show "Powered by chainkit"',
							'chainkit-bitcoin-payment-button'
						) }
						checked={ showPowered }
						onChange={ ( v ) =>
							setAttributes( { showPowered: v } )
						}
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ uri ? (
					<>
						<div className="chainkit-bpb__head">
							<span className="chainkit-bpb__eyebrow">
								<span
									className={ `chainkit-bpb__dot${
										amountMode === 'fiat' ? ' is-live' : ''
									}` }
									aria-hidden="true"
								/>
								{ __(
									'Bitcoin payment',
									'chainkit-bitcoin-payment-button'
								) }
							</span>
							<Mark />
						</div>

						<div className="chainkit-bpb__body">
							{ showQr && (
								<div className="chainkit-bpb__qr-preview">
									{ __(
										'QR',
										'chainkit-bitcoin-payment-button'
									) }
								</div>
							) }
							<div className="chainkit-bpb__pay">
								<div className="chainkit-bpb__amount">
									{ heroText && (
										<div className="chainkit-bpb__hero">
											{ heroText }
										</div>
									) }
									{ presetChips.length > 0 && (
										<div className="chainkit-bpb__presets">
											{ presetChips.map( ( chip, i ) => (
												<span
													key={ i }
													className={ `chainkit-bpb__preset${
														i === 0
															? ' is-active'
															: ''
													}` }
												>
													{ chip }
												</span>
											) ) }
										</div>
									) }
									{ subText && (
										<div className="chainkit-bpb__sub">
											{ subText }
										</div>
									) }
									{ isApprox && (
										<div className="chainkit-bpb__note">
											{ __(
												'Approximate — rate not locked.',
												'chainkit-bitcoin-payment-button'
											) }
										</div>
									) }
								</div>
								<span
									className="chainkit-bpb__btn"
									role="button"
								>
									<span className="chainkit-bpb__btn-text">
										{ buttonText }
									</span>
								</span>
							</div>
						</div>

						<div className="chainkit-bpb__addr-row">
							<span className="chainkit-bpb__addr-label">
								{ __(
									'Pay to',
									'chainkit-bitcoin-payment-button'
								) }
							</span>
							<code className="chainkit-bpb__addr">{ addr }</code>
						</div>

						{ showPowered && (
							<span className="chainkit-bpb__powered">
								<Mark />
								<span>
									{ __(
										'Powered by',
										'chainkit-bitcoin-payment-button'
									) }
									<strong>chainkit</strong>
								</span>
							</span>
						) }
					</>
				) : (
					<p className="chainkit-bpb__placeholder">
						{ __(
							'Enter a Bitcoin address here or under Settings → Bitcoin Payment Button to render the button.',
							'chainkit-bitcoin-payment-button'
						) }
					</p>
				) }
			</div>
		</>
	);
}

/** The chainkit mark — three ledger rows and a lime accent bar. */
function Mark() {
	return (
		<svg
			className="chainkit-bpb__mark"
			width="20"
			height="20"
			viewBox="0 0 24 24"
			aria-hidden="true"
		>
			<rect x="11" y="3" width="2" height="18" fill="currentColor" />
			<rect x="2" y="5" width="8" height="2" fill="currentColor" />
			<rect x="2" y="11" width="8" height="2" fill="currentColor" />
			<rect x="2" y="17" width="8" height="2" fill="currentColor" />
			<rect x="14" y="11" width="8" height="2" fill="#bfdb00" />
		</svg>
	);
}
