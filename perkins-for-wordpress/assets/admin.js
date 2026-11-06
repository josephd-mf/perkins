document.addEventListener( 'DOMContentLoaded', function () {
	var body = document.getElementById( 'es-criteria-body' );
	var tpl = document.getElementById( 'es-row-template' );
	var add = document.getElementById( 'es-add-criterion' );
	if ( ! body || ! tpl || ! add ) {
		return;
	}
	var next = 0;
	add.addEventListener( 'click', function () {
		var html = tpl.innerHTML.replace( /__INDEX__/g, 'n' + next++ );
		body.insertAdjacentHTML( 'beforeend', html );
	} );
	body.addEventListener( 'click', function ( e ) {
		if ( e.target.classList.contains( 'es-remove' ) ) {
			e.preventDefault();
			var row = e.target.closest( 'tr' );
			if ( row ) {
				row.remove();
			}
		}
	} );
} );
