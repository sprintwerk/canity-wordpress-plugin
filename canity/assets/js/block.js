( function ( blocks, element, i18n, components, blockEditor ) {
	const { registerBlockType } = blocks;
	const { createElement: el, Fragment } = element;
	const { __ } = i18n;
	const { PanelBody, SelectControl, RangeControl, Placeholder } = components;
	const { InspectorControls, useBlockProps } = blockEditor;

	registerBlockType( 'canity/list', {
		title: __( 'CANITY List', 'canity' ),
		description: __( 'Displays services, events, or packages from the CANITY API.', 'canity' ),
		icon: 'list-view',
		category: 'widgets',
		attributes: {
			type: { type: 'string', default: 'services' },
			limit: { type: 'number', default: 0 },
			detail: { type: 'string', default: '' },
		},
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps();

			const typeLabels = {
				services: __( 'Services', 'canity' ),
				events: __( 'Events', 'canity' ),
				packages: __( 'Packages', 'canity' ),
			};

			const detailLabels = {
				'': __( 'Global (settings)', 'canity' ),
				external: __( 'External (canity.de)', 'canity' ),
				modal: __( 'Dialog', 'canity' ),
				page: __( 'Dedicated page', 'canity' ),
				inline: __( 'Expand inline', 'canity' ),
			};

			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Settings', 'canity' ) },
						el( SelectControl, {
							label: __( 'Content', 'canity' ),
							value: attributes.type,
							options: [
								{ label: typeLabels.services, value: 'services' },
								{ label: typeLabels.events, value: 'events' },
								{ label: typeLabels.packages, value: 'packages' },
							],
							onChange: function ( value ) {
								setAttributes( { type: value } );
							},
						} ),
						el( RangeControl, {
							label: __( 'Count (0 = all)', 'canity' ),
							value: attributes.limit,
							min: 0,
							max: 50,
							onChange: function ( value ) {
								setAttributes( { limit: value || 0 } );
							},
						} ),
						el( SelectControl, {
							label: __( 'Detail view', 'canity' ),
							value: attributes.detail,
							options: [
								{ label: detailLabels[''], value: '' },
								{ label: detailLabels.external, value: 'external' },
								{ label: detailLabels.modal, value: 'modal' },
								{ label: detailLabels.page, value: 'page' },
								{ label: detailLabels.inline, value: 'inline' },
							],
							onChange: function ( value ) {
								setAttributes( { detail: value } );
							},
						} )
					)
				),
				el(
					'div',
					blockProps,
					el(
						Placeholder,
						{
							icon: 'list-view',
							label: __( 'CANITY List', 'canity' ),
							instructions:
								typeLabels[ attributes.type ] +
								( attributes.limit ? ' · ' + __( 'Limit:', 'canity' ) + ' ' + attributes.limit : '' ) +
								( attributes.detail ? ' · ' + detailLabels[ attributes.detail ] : '' ),
						}
					)
				)
			);
		},
		save: function () {
			// Server-rendered via render_callback.
			return null;
		},
	} );

	registerBlockType( 'canity/detail', {
		title: __( 'CANITY Detail', 'canity' ),
		description: __(
			'Shows the detail view of a service, event, or package when linked from a CANITY list.',
			'canity'
		),
		icon: 'media-document',
		category: 'widgets',
		keywords: [
			__( 'canity', 'canity' ),
			__( 'detail', 'canity' ),
			__( 'booking', 'canity' ),
		],
		attributes: {},
		edit: function ( props ) {
			const blockProps = useBlockProps();

			return el(
				'div',
				blockProps,
				el( Placeholder, {
					icon: 'media-document',
					label: __( 'CANITY Detail', 'canity' ),
					instructions: __(
						'Place on the detail page for the "Dedicated page" mode. On the frontend, details appear when visitors follow a link from a CANITY list. Select this page under Settings → CANITY as the detail page.',
						'canity'
					),
				} )
			);
		},
		save: function () {
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.i18n,
	window.wp.components,
	window.wp.blockEditor
);
