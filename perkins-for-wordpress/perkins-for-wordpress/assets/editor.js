( function ( wp, cfg ) {
	var h = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var Fragment = wp.element.Fragment;
	var useSelect = wp.data.useSelect;
	var Button = wp.components.Button;
	var Spinner = wp.components.Spinner;
	var __ = wp.i18n.__;

	var ed = wp.editor || {};
	var ep = wp.editPost || {};
	var DocPanel = ed.PluginDocumentSettingPanel || ep.PluginDocumentSettingPanel;
	var PrePublish = ed.PluginPrePublishPanel || ep.PluginPrePublishPanel;

	function fmt( n ) {
		return String( Math.round( n * 10 ) / 10 );
	}

	function Results( props ) {
		var r = props.review;
		var color = r.passed ? '#007017' : '#b32d2e';
		return h(
			'div',
			{ style: { marginTop: 12 } },
			h(
				'p',
				{ style: { fontWeight: 600, color: color, margin: '0 0 8px' } },
				r.passed
					? __( 'Meets editorial standards', 'perkins-for-wordpress' )
					: __( 'Does not meet editorial standards', 'perkins-for-wordpress' )
			),
			r.summary ? h( 'p', { style: { margin: '0 0 8px' } }, r.summary ) : null,
			r.criteria.map( function ( c ) {
				return h(
					'div',
					{ key: c.id, style: { borderTop: '1px solid #ddd', padding: '8px 0' } },
					h(
						'div',
						{ style: { display: 'flex', justifyContent: 'space-between', fontWeight: 600 } },
						h( 'span', null, c.label ),
						h( 'span', { style: { color: c.passed ? '#007017' : '#b32d2e' } }, fmt( c.score ) + ' / ' + c.min )
					),
					c.feedback ? h( 'div', { style: { fontSize: 12, color: '#50575e', marginTop: 2 } }, c.feedback ) : null
				);
			} )
		);
	}

	function Body( props ) {
		var s1 = useState( false ), loading = s1[ 0 ], setLoading = s1[ 1 ];
		var s2 = useState( null ), data = s2[ 0 ], setData = s2[ 1 ];
		var s3 = useState( null ), error = s3[ 0 ], setError = s3[ 1 ];
		var s4 = useState( false ), applied = s4[ 0 ], setApplied = s4[ 1 ];

		function run( opts ) {
			opts = opts || {};
			var e = wp.data.select( 'core/editor' );
			setLoading( true );
			setError( null );
			return wp
				.apiFetch( {
					path: '/perkins/v1/review',
					method: 'POST',
					data: {
						post_id: e.getCurrentPostId(),
						title: e.getEditedPostAttribute( 'title' ) || '',
						content: e.getEditedPostContent(),
						force: !! opts.force,
						redraft: !! opts.redraft,
					},
				} )
				.then( function ( res ) {
					setData( res );
					setLoading( false );
				} )
				.catch( function ( err ) {
					setError( ( err && err.message ) || __( 'Review failed.', 'perkins-for-wordpress' ) );
					setLoading( false );
				} );
		}

		function applyRedraft( rd ) {
			var blocks = wp.blocks.parse( rd.content );
			wp.data.dispatch( 'core/block-editor' ).resetBlocks( blocks );
			wp.data.dispatch( 'core/editor' ).editPost( { title: rd.title } );
			setApplied( true );
			setData( Object.assign( {}, data, { redraft: null, review: rd.review } ) );
		}

		useEffect( function () {
			if ( props.autoRun ) {
				run();
			}
		}, [] );

		var review = data && data.review;
		var redraft = data && data.redraft;

		return h(
			'div',
			null,
			cfg.iconUrl
				? h(
						'div',
						{ style: { display: 'flex', alignItems: 'center', gap: 8, marginBottom: 8 } },
						h( 'img', { src: cfg.iconUrl, alt: '', width: 28, height: 28 } ),
						h( 'strong', null, 'Perkins' )
				  )
				: null,
			h(
				'p',
				{ style: { fontSize: 12, color: '#50575e', marginTop: 0 } },
				cfg.mode === 'block'
					? __( 'Publishing is blocked until the article meets every minimum score.', 'perkins-for-wordpress' )
					: __( 'You will be warned if the article falls below the minimum scores.', 'perkins-for-wordpress' )
			),
			h(
				Button,
				{ variant: 'secondary', onClick: function () { setApplied( false ); run( { force: true } ); }, disabled: loading },
				review ? __( 'Re-run review', 'perkins-for-wordpress' ) : __( 'Review article', 'perkins-for-wordpress' )
			),
			loading ? h( Spinner, null ) : null,
			error ? h( 'p', { style: { color: '#b32d2e' } }, error ) : null,
			applied ? h( 'p', { style: { color: '#007017' } }, __( 'Redraft applied to the editor. Review it before publishing.', 'perkins-for-wordpress' ) ) : null,
			review ? h( Results, { review: review } ) : null,

			review && ! review.passed && cfg.allowRedraft && ! redraft
				? h(
						'div',
						{ style: { marginTop: 12 } },
						h(
							Button,
							{ variant: 'primary', onClick: function () { run( { redraft: true } ); }, disabled: loading },
							__( 'Redraft with AI', 'perkins-for-wordpress' )
						)
				  )
				: null,

			data && data.redraftError ? h( 'p', { style: { color: '#b32d2e' } }, data.redraftError ) : null,

			redraft
				? h(
						'div',
						{ style: { marginTop: 12, padding: 10, background: '#f6f7f7', border: '1px solid #ddd' } },
						h(
							'p',
							{ style: { margin: '0 0 6px', fontWeight: 600 } },
							redraft.passed
								? __( 'AI redraft ready: meets all criteria', 'perkins-for-wordpress' )
								: __( 'AI redraft ready: improved, but still below some minimums', 'perkins-for-wordpress' )
						),
						h( 'p', { style: { margin: '0 0 6px', fontSize: 12 } }, redraft.title ),
						h(
							'details',
							null,
							h( 'summary', null, __( 'Redraft scores', 'perkins-for-wordpress' ) ),
							h( Results, { review: redraft.review } )
						),
						h(
							'div',
							{ style: { marginTop: 8 } },
							h( Button, { variant: 'primary', onClick: function () { applyRedraft( redraft ); } }, __( 'Apply redraft', 'perkins-for-wordpress' ) )
						),
						h( 'p', { style: { fontSize: 11, color: '#50575e', marginBottom: 0 } }, __( 'Replaces the current content. You can undo with Ctrl/Cmd+Z.', 'perkins-for-wordpress' ) )
				  )
				: null
		);
	}

	wp.plugins.registerPlugin( 'perkins-for-wordpress', {
		render: function () {
			var type = useSelect( function ( select ) {
				return select( 'core/editor' ).getCurrentPostType();
			}, [] );
			if ( ! type || cfg.postTypes.indexOf( type ) === -1 ) {
				return null;
			}
			return h(
				Fragment,
				null,
				DocPanel
					? h(
							DocPanel,
							{ name: 'perkins-panel', title: __( 'Perkins', 'perkins-for-wordpress' ) },
							h( Body, null )
					  )
					: null,
				PrePublish
					? h(
							PrePublish,
							{ title: __( 'Perkins', 'perkins-for-wordpress' ), initialOpen: true },
							h( Body, { autoRun: true } )
					  )
					: null
			);
		},
	} );
} )( window.wp, window.ESConfig || { postTypes: [], mode: 'block', allowRedraft: false } );
