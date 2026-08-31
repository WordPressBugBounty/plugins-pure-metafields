/* PureFields Help Page — tabs · copy · highlight · flow animation · scroll-spy */
(function () {
	'use strict';

	/* ── Syntax highlighter ──────────────────────────────────────
	   Tokenises text sequentially so tokens never overlap.       */
	var PHP_KW  = /^(function|return|foreach|if|else|elseif|new|array|echo|true|false|null|class)\b/;
	var PHP_FN  = /^(add_filter|add_action|tpmeta_field|tpmeta_image_field|tpmeta_gallery_field|tpmeta_image|tpmeta_get_option|tpmeta_get_repeater_rows|get_theme_mod|get_user_meta|update_user_meta|get_current_user_id|get_the_author_meta|esc_html|esc_url|esc_attr|current_user_can|wp_die|TPMeta_Options|set_args|set_section)\b/;
	var PHP_VAR = /^(\$[a-zA-Z_]\w*)/;
	var PHP_ST1 = /^('(?:[^'\\]|\\.)*')/;
	var PHP_ST2 = /^("(?:[^"\\]|\\.)*")/;
	var PHP_CM  = /^(\/\/[^\n]*|\/\*[\s\S]*?\*\/|#[^\n]*)/;
	var PHP_NB  = /^(\b\d+\b)/;

	function tokenise(src) {
		var out = [];
		var rem = src;
		while (rem.length) {
			var m;
			if ((m = rem.match(PHP_CM)))  { out.push(['syn-cm',  m[1]]); rem = rem.slice(m[1].length); continue; }
			if ((m = rem.match(PHP_ST1))) { out.push(['syn-st',  m[1]]); rem = rem.slice(m[1].length); continue; }
			if ((m = rem.match(PHP_ST2))) { out.push(['syn-st',  m[1]]); rem = rem.slice(m[1].length); continue; }
			if ((m = rem.match(PHP_VAR))) { out.push(['syn-var', m[1]]); rem = rem.slice(m[1].length); continue; }
			if ((m = rem.match(PHP_FN)))  { out.push(['syn-fn',  m[1]]); rem = rem.slice(m[1].length); continue; }
			if ((m = rem.match(PHP_KW)))  { out.push(['syn-kw',  m[1]]); rem = rem.slice(m[1].length); continue; }
			if ((m = rem.match(PHP_NB)))  { out.push(['syn-nb',  m[1]]); rem = rem.slice(m[1].length); continue; }
			out.push([null, rem[0]]);
			rem = rem.slice(1);
		}
		return out;
	}

	function esc(s) { return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

	function highlight(src) {
		return tokenise(src).map(function (t) {
			var e = esc(t[1]);
			return t[0] ? '<span class="' + t[0] + '">' + e + '</span>' : e;
		}).join('');
	}

	function initHighlight() {
		document.querySelectorAll('.pf-pre[data-lang]').forEach(function (pre) {
			pre.innerHTML = highlight(pre.textContent);
		});
	}

	/* ── Flow animation ──────────────────────────────────────── */
	function playFlow(tab) {
		var flow = document.getElementById('flow-' + tab);
		if (!flow) return;

		var steps  = flow.querySelectorAll('.pf-flow-step');
		var conns  = flow.querySelectorAll('.pf-flow-conn');

		steps.forEach(function (s) { s.classList.remove('vis'); });
		conns.forEach(function (c) { c.classList.remove('vis'); });

		steps.forEach(function (s, i) {
			setTimeout(function () { s.classList.add('vis'); }, i * 220 + 80);
		});
		conns.forEach(function (c, i) {
			setTimeout(function () { c.classList.add('vis'); }, i * 220 + 240);
		});
	}

	/* ── Tab switching ───────────────────────────────────────── */
	function initTabs() {
		var pills     = document.querySelectorAll('.pf-tab-pill');
		var panels    = document.querySelectorAll('.pf-panel');
		var navGroups = document.querySelectorAll('.pf-nav-group');

		function activate(tab) {
			pills.forEach(function (p) {
				p.classList.toggle('active', p.dataset.tab === tab);
			});
			panels.forEach(function (p) {
				p.classList.toggle('hidden', p.dataset.tab !== tab);
			});
			navGroups.forEach(function (g) {
				g.classList.toggle('active', g.dataset.tab === tab);
			});
			playFlow(tab);
		}

		pills.forEach(function (pill) {
			pill.addEventListener('click', function () { activate(this.dataset.tab); });
		});

		/* boot the first tab */
		activate('metafields');
	}

	/* ── Copy buttons ────────────────────────────────────────── */
	function initCopy() {
		document.addEventListener('click', function (e) {
			var btn = e.target.closest('.pf-copy-btn');
			if (!btn) return;
			var card = btn.closest('.pf-code-card');
			if (!card) return;
			var pre  = card.querySelector('.pf-pre');
			if (!pre) return;

			var text = pre.textContent;

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function () { flash(btn); });
			} else {
				/* fallback */
				var ta = document.createElement('textarea');
				ta.value = text;
				ta.style.position = 'fixed';
				ta.style.opacity = '0';
				document.body.appendChild(ta);
				ta.select();
				try { document.execCommand('copy'); flash(btn); } catch (_) {}
				document.body.removeChild(ta);
			}
		});

		function flash(btn) {
			btn.classList.add('ok');
			btn.innerHTML = '<span class="dashicons dashicons-yes-alt"></span> Copied!';
			setTimeout(function () {
				btn.classList.remove('ok');
				btn.innerHTML = '<span class="dashicons dashicons-clipboard"></span> Copy';
			}, 2000);
		}
	}

	/* ── Scroll spy ──────────────────────────────────────────── */
	function initScrollSpy() {
		if (!window.IntersectionObserver) return;
		var links = document.querySelectorAll('.pf-nav a[href^="#"]');

		var obs = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) return;
				var id = '#' + entry.target.id;
				links.forEach(function (a) {
					a.classList.toggle('active', a.getAttribute('href') === id);
				});
			});
		}, { threshold: 0.25, rootMargin: '-60px 0px -55% 0px' });

		document.querySelectorAll('.pf-section[id]').forEach(function (s) { obs.observe(s); });
	}

	/* ── Smooth-scroll sidebar links ─────────────────────────── */
	function initScrollLinks() {
		document.addEventListener('click', function (e) {
			var a = e.target.closest('.pf-nav a[href^="#"]');
			if (!a) return;
			e.preventDefault();
			var target = document.querySelector(a.getAttribute('href'));
			if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
		});
	}


	/* ── Boot ────────────────────────────────────────────────── */
	document.addEventListener('DOMContentLoaded', function () {
		initHighlight();
		initTabs();
		initCopy();
		initScrollSpy();
		initScrollLinks();
	});
}());
