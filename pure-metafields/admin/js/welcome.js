/**
 * Pure Fields — Dashboard JS
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', fixAdminOverflow);

	function fixAdminOverflow() {
		[ 'wpwrap', 'wpbody', 'wpbody-content', 'wpcontent' ].forEach(function (id) {
			var el = document.getElementById(id);
			if (el) el.style.overflow = 'visible';
		});
	}

}());
