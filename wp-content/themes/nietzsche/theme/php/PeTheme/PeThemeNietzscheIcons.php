<?php

class PeThemeNietzscheIcons {

	public function __construct() {

		// Load font files in admin
		add_action( 'pe_theme_admin_icons_css_path', array( $this, 'admin_icons_css_path' ) );

		// Define a list of icons
		add_filter( 'pe_theme_icons_list', array( $this, 'get_icons_list' ) );

	}

	public function admin_icons_css_path( $path ) {

		return array(
			'css/admin-icons.css',
			//'plugins/elegant_font/html_css/style.css',
		);

	}

	public function get_icons_list( $icons ) {

		$icons = array(
			'icon-note',
			'icon-logo-db',
			'icon-music',
			'icon-search',
			'icon-flashlight',
			'icon-mail',
			'icon-heart',
			'icon-heart-empty',
			'icon-star',
			'icon-star-empty',
			'icon-user',
			'icon-users',
			'icon-user-add',
			'icon-video',
			'icon-picture',
			'icon-camera',
			'icon-layout',
			'icon-menu',
			'icon-check',
			'icon-cancel',
			'icon-cancel-circled',
			'icon-cancel-squared',
			'icon-plus',
			'icon-plus-circled',
			'icon-plus-squared',
			'icon-minus',
			'icon-minus-circled',
			'icon-minus-squared',
			'icon-help',
			'icon-help-circled',
			'icon-info',
			'icon-info-circled',
			'icon-back',
			'icon-home',
			'icon-link',
			'icon-attach',
			'icon-lock',
			'icon-lock-open',
			'icon-eye',
			'icon-tag',
			'icon-bookmark',
			'icon-bookmarks',
			'icon-flag',
			'icon-thumbs-up',
			'icon-thumbs-down',
			'icon-download',
			'icon-upload',
			'icon-upload-cloud',
			'icon-reply',
			'icon-reply-all',
			'icon-forward',
			'icon-quote',
			'icon-code',
			'icon-export',
			'icon-pencil',
			'icon-feather',
			'icon-print',
			'icon-retweet',
			'icon-keyboard',
			'icon-comment',
			'icon-chat',
			'icon-bell',
			'icon-attention',
			'icon-alert',
			'icon-vcard',
			'icon-address',
			'icon-location',
			'icon-map',
			'icon-direction',
			'icon-compass',
			'icon-cup',
			'icon-trash',
			'icon-doc',
			'icon-docs',
			'icon-doc-landscape',
			'icon-doc-text',
			'icon-doc-text-inv',
			'icon-newspaper',
			'icon-book-open',
			'icon-book',
			'icon-folder',
			'icon-archive',
			'icon-box',
			'icon-rss',
			'icon-phone',
			'icon-cog',
			'icon-tools',
			'icon-share',
			'icon-shareable',
			'icon-basket',
			'icon-bag',
			'icon-calendar',
			'icon-login',
			'icon-logout',
			'icon-mic',
			'icon-mute',
			'icon-sound',
			'icon-volume',
			'icon-clock',
			'icon-hourglass',
			'icon-lamp',
			'icon-light-down',
			'icon-light-up',
			'icon-adjust',
			'icon-block',
			'icon-resize-full',
			'icon-resize-small',
			'icon-popup',
			'icon-publish',
			'icon-window',
			'icon-arrow-combo',
			'icon-down-circled',
			'icon-left-circled',
			'icon-right-circled',
			'icon-up-circled',
			'icon-down-open',
			'icon-left-open',
			'icon-right-open',
			'icon-up-open',
			'icon-down-open-mini',
			'icon-left-open-mini',
			'icon-right-open-mini',
			'icon-up-open-mini',
			'icon-down-open-big',
			'icon-left-open-big',
			'icon-right-open-big',
			'icon-up-open-big',
			'icon-down',
			'icon-left',
			'icon-right',
			'icon-up',
			'icon-down-dir',
			'icon-left-dir',
			'icon-right-dir',
			'icon-up-dir',
			'icon-down-bold',
			'icon-left-bold',
			'icon-right-bold',
			'icon-up-bold',
			'icon-down-thin',
			'icon-left-thin',
			'icon-right-thin',
			'icon-note-beamed',
			'icon-ccw',
			'icon-cw',
			'icon-arrows-ccw',
			'icon-level-down',
			'icon-level-up',
			'icon-shuffle',
			'icon-loop',
			'icon-switch',
			'icon-play',
			'icon-stop',
			'icon-pause',
			'icon-record',
			'icon-to-end',
			'icon-to-start',
			'icon-fast-forward',
			'icon-fast-backward',
			'icon-progress-0',
			'icon-progress-1',
			'icon-progress-2',
			'icon-progress-3',
			'icon-target',
			'icon-palette',
			'icon-list',
			'icon-list-add',
			'icon-signal',
			'icon-trophy',
			'icon-battery',
			'icon-back-in-time',
			'icon-monitor',
			'icon-mobile',
			'icon-network',
			'icon-cd',
			'icon-inbox',
			'icon-install',
			'icon-globe',
			'icon-cloud',
			'icon-cloud-thunder',
			'icon-flash',
			'icon-moon',
			'icon-flight',
			'icon-paper-plane',
			'icon-leaf',
			'icon-lifebuoy',
			'icon-mouse',
			'icon-briefcase',
			'icon-suitcase',
			'icon-dot',
			'icon-dot-2',
			'icon-dot-3',
			'icon-brush',
			'icon-magnet',
			'icon-infinity',
			'icon-erase',
			'icon-chart-pie',
			'icon-chart-line',
			'icon-chart-bar',
			'icon-chart-area',
			'icon-tape',
			'icon-graduation-cap',
			'icon-language',
			'icon-ticket',
			'icon-water',
			'icon-droplet',
			'icon-air',
			'icon-credit-card',
			'icon-floppy',
			'icon-clipboard',
			'icon-megaphone',
			'icon-database',
			'icon-drive',
			'icon-bucket',
			'icon-thermometer',
			'icon-key',
			'icon-flow-cascade',
			'icon-flow-branch',
			'icon-flow-tree',
			'icon-flow-line',
			'icon-flow-parallel',
			'icon-rocket',
			'icon-gauge',
			'icon-traffic-cone',
			'icon-cc',
			'icon-cc-by',
			'icon-cc-nc',
			'icon-cc-nc-eu',
			'icon-cc-nc-jp',
			'icon-cc-sa',
			'icon-cc-nd',
			'icon-cc-pd',
			'icon-cc-zero',
			'icon-cc-share',
			'icon-cc-remix',
			'icon-github',
			'icon-github-circled',
			'icon-flickr',
			'icon-flickr-circled',
			'icon-vimeo',
			'icon-vimeo-circled',
			'icon-twitter',
			'icon-twitter-circled',
			'icon-facebook',
			'icon-facebook-circled',
			'icon-facebook-squared',
			'icon-gplus',
			'icon-gplus-circled',
			'icon-pinterest',
			'icon-pinterest-circled',
			'icon-tumblr',
			'icon-tumblr-circled',
			'icon-linkedin',
			'icon-linkedin-circled',
			'icon-dribbble',
			'icon-dribbble-circled',
			'icon-stumbleupon',
			'icon-stumbleupon-circled',
			'icon-lastfm',
			'icon-lastfm-circled',
			'icon-rdio',
			'icon-rdio-circled',
			'icon-spotify',
			'icon-spotify-circled',
			'icon-qq',
			'icon-instagram',
			'icon-dropbox',
			'icon-evernote',
			'icon-flattr',
			'icon-skype',
			'icon-skype-circled',
			'icon-renren',
			'icon-sina-weibo',
			'icon-paypal',
			'icon-picasa',
			'icon-soundcloud',
			'icon-mixi',
			'icon-behance',
			'icon-google-circles',
			'icon-vkontakte',
			'icon-smashing',
			'icon-sweden',
			'icon-db-shape',
			'icon-up-thin',
		);

		return $icons;

	}

}