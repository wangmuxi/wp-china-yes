<?php

namespace WenPai\ChinaYes\Service;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use function WenPai\ChinaYes\get_settings;

/**
 * Class Super
 * 插件加速服务
 * @package WenPai\ChinaYes\Service
 */
class Super {

	private $settings;

	public function __construct() {
		$this->settings = get_settings();

		/**
		 * WordPress.Org API 替换
		 */
		if ( is_admin() || wp_doing_cron() ) {
			if ( $this->settings['store'] != 'off' ) {
				add_filter( 'pre_http_request', [ $this, 'filter_wordpress_org' ], 100, 3 );
			}
		}

		/**
		 * 添加「文派茶馆」小组件
		 */
		if ( is_admin() ) {
			add_action( 'wp_dashboard_setup', function () {
				global $wp_meta_boxes;

				unset( $wp_meta_boxes['dashboard']['side']['core']['dashboard_primary'] );
				wp_add_dashboard_widget( 'wenpai_tea', '文派茶馆', function () {
					echo <<<HTML
					<div class="wordpress-news hide-if-no-js">
					<div class="rss-widget">
HTML;
					wp_widget_rss_output( 'https://wptea.com/feed/', [
						'items'        => 5,
						'show_summary' => 1,
					] );
					echo <<<HTML
					</div>
					</div>
					<p class="community-events-footer">
					<a href="https://wenpai.org/" target="_blank">文派开源</a>
					 |
					<a href="https://wenpai.org/support" target="_blank">支持论坛</a>
					 |
					<a href="https://translate.wenpai.org/" target="_blank">翻译平台</a>
					 |
					<a href="https://wptea.com/newsletter/" target="_blank">订阅推送</a>
					</p>
					<style>
						#wenpai_tea .rss-widget {
						  font-size:13px;
						  padding:0 12px
						}
						#wenpai_tea .rss-widget:last-child {
						  border-bottom:none;
						  padding-bottom:8px
						}
						#wenpai_tea .rss-widget a {
						  font-weight:400
						}
						#wenpai_tea .rss-widget span,
						#wenpai_tea .rss-widget span.rss-date {
						  color:#646970
						}
						#wenpai_tea .rss-widget span.rss-date {
						  margin-left:12px
						}
						#wenpai_tea .rss-widget ul li {
						  padding:4px 0;
						  margin:0
						}
						#wenpai_tea .community-events-footer a {
						  line-height: 2;
						  padding: 0.5em;
						}
					</style>
HTML;
				} );
			} );
			add_action( 'wp_network_dashboard_setup', function () {
				global $wp_meta_boxes;

				unset( $wp_meta_boxes['dashboard-network']['side']['core']['dashboard_primary'] );
				wp_add_dashboard_widget( 'wenpai_tea', '文派茶馆', function () {
					echo <<<HTML
					<div class="wordpress-news hide-if-no-js">
					<div class="rss-widget">
HTML;
					wp_widget_rss_output( 'https://wptea.com/feed/', [
						'items'        => 5,
						'show_summary' => 1,
					] );
					echo <<<HTML
					</div>
					</div>
					<p class="community-events-footer">
					<a href="https://wenpai.org/" target="_blank">文派开源</a>
					 |
					<a href="https://wenpai.org/support" target="_blank">支持论坛</a>
					 |
					 <a href="https://translate.wenpai.org/" target="_blank">翻译平台</a>
 				   |
			  	 <a href="https://wptea.com/newsletter/" target="_blank">订阅推送</a>
			    </p>
					<style>
						#wenpai_tea .rss-widget {
						  font-size:13px;
						  padding:0 12px
						}
						#wenpai_tea .rss-widget:last-child {
						  border-bottom:none;
						  padding-bottom:8px
						}
						#wenpai_tea .rss-widget a {
						  font-weight:400
						}
						#wenpai_tea .rss-widget span,
						#wenpai_tea .rss-widget span.rss-date {
						  color:#646970
						}
						#wenpai_tea .rss-widget span.rss-date {
						  margin-left:12px
						}
						#wenpai_tea .rss-widget ul li {
						  padding:4px 0;
						  margin:0
						}
						#wenpai_tea .community-events-footer a {
							line-height: 2;
							padding: 0.5em;
						}
					</style>
HTML;
				} );
			} );
		}

		/**
		 * WordPress 核心静态文件链接替换
		 */
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			if (
				in_array( 'admin', (array) $this->settings['admincdn'] ) &&
				! stristr( $GLOBALS['wp_version'], 'alpha' ) &&
				! stristr( $GLOBALS['wp_version'], 'beta' ) &&
				! stristr( $GLOBALS['wp_version'], 'RC' )
			) {
				// 禁用合并加载，以便于使用公共资源节点
				global $concatenate_scripts;
				$concatenate_scripts = false;

				$this->page_str_replace( 'init', 'preg_replace', [
					'~' . home_url( '/' ) . '(wp-admin|wp-includes)/(css|js)/~',
					sprintf( 'https://wpstatic.admincdn.com/%s/$1/$2/', $GLOBALS['wp_version'] )
				] );
			}
		}

		/**
		 * adminCDN
		 */
		if ( ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			$this->load_admincdn();
		}

		/**
		 * 初认头像
		 */
		if ( ! empty( $this->settings['cravatar'] ) ) {
			add_filter( 'user_profile_picture_description', [ $this, 'set_user_profile_picture_for_cravatar' ], 1 );
			add_filter( 'avatar_defaults', [ $this, 'set_defaults_for_cravatar' ], 1 );
			add_filter( 'um_user_avatar_url_filter', [ $this, 'get_cravatar_url' ], 1 );
			add_filter( 'bp_gravatar_url', [ $this, 'get_cravatar_url' ], 1 );
			add_filter( 'get_avatar_url', [ $this, 'get_cravatar_url' ], 1 );
		}

		/**
		 * 文风字体
		 */
		if ( ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			if ( ! empty( $this->settings['windfonts'] ) && $this->settings['windfonts'] != 'off' ) {
				$this->load_typography();
			}
			if ( ! empty( $this->settings['windfonts'] ) && $this->settings['windfonts'] == 'optimize' ) {
				add_action( 'init', function () {
					wp_enqueue_style( 'windfonts-optimize', CHINA_YES_PLUGIN_URL . 'assets/css/fonts.css', [], CHINA_YES_VERSION );
				} );
			}
			if ( ! empty( $this->settings['windfonts'] ) && $this->settings['windfonts'] == 'on' ) {
				add_action( 'wp_head', [ $this, 'load_windfonts' ] );
				add_action( 'admin_head', [ $this, 'load_windfonts' ] );
			}
			if ( ! empty( $this->settings['windfonts'] ) && $this->settings['windfonts'] == 'frontend' ) {
				add_action( 'wp_head', [ $this, 'load_windfonts' ] );
			}
		}

		/**
		 * 广告拦截
		 */
		if ( ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			if ( ! empty( $this->settings['adblock'] ) && $this->settings['adblock'] == 'on' ) {
				add_action( 'admin_head', [ $this, 'load_adblock' ] );
			}
		}

		/**
		 * 飞行模式
		 */
		if ( ! empty( $this->settings['plane'] ) && $this->settings['plane'] == 'on' ) {
			$this->load_plane();
		}
	}

	/**
	 * 加载 adminCDN
	 */
	public function load_admincdn() {
		/**
		 * 前台静态加速
		 */
		if ( in_array( 'frontend', (array) $this->settings['admincdn'] ) ) {
			$this->page_str_replace( 'template_redirect', 'preg_replace', [
				'#(?<=[(\"\'])(?:' . quotemeta( home_url() ) . ')?/(?:((?:wp-content|wp-includes)[^\"\')]+\.(css|js)[^\"\')]+))(?=[\"\')])#',
				'https://public.admincdn.com/$0'
			] );
		}

		/**
		 * Google 字体替换
		 */
		if ( in_array( 'googlefonts', (array) $this->settings['admincdn'] ) ) {
			$this->page_str_replace( 'init', 'str_replace', [
				'fonts.googleapis.com',
				'googlefonts.admincdn.com'
			] );
		}

		/**
		 * Google 前端公共库替换
		 */
		if ( in_array( 'googleajax', (array) $this->settings['admincdn'] ) ) {
			$this->page_str_replace( 'init', 'str_replace', [
				'ajax.googleapis.com',
				'googleajax.admincdn.com'
			] );
		}

		/**
		 * CDNJS 前端公共库替换
		 */
		if ( in_array( 'cdnjs', (array) $this->settings['admincdn'] ) ) {
			$this->page_str_replace( 'init', 'str_replace', [
				'cdnjs.cloudflare.com/ajax/libs',
				'cdnjs.admincdn.com'
			] );
		}

		/**
		 * jsDelivr 前端公共库替换
		 */
		if ( in_array( 'jsdelivr', (array) $this->settings['admincdn'] ) ) {
			$this->page_str_replace( 'init', 'str_replace', [
				'cdn.jsdelivr.net',
				'jsd.admincdn.com'
			] );
		}

		/**
		 * BootstrapCDN 前端公共库替换
		 */
		if ( in_array( 'bootstrapcdn', (array) $this->settings['admincdn'] ) ) {
			$this->page_str_replace( 'init', 'str_replace', [
				'maxcdn.bootstrapcdn.com',
				'jsd.admincdn.com'
			] );
		}
	}


	/**
	 * 加载文风字体
	 */
	public function load_windfonts() {
		echo <<<HTML
		<link rel="preconnect" href="//cn.windfonts.com">
		<!-- 此中文网页字体由文风字体（Windfonts）免费提供，您可以自由引用，请务必保留此授权许可标注 https://wenfeng.org/license -->
HTML;

		$loaded = [];
		foreach ( (array) $this->settings['windfonts_list'] as $font ) {
			if ( empty( $font['enable'] ) ) {
				continue;
			}
			if ( empty( $font['family'] ) ) {
				continue;
			}
			if ( in_array( $font['css'], $loaded ) ) {
				continue;
			}
			echo sprintf( <<<HTML
			<link rel="stylesheet" type="text/css" href="%s">
			<style>
			%s {
				font-style: %s;
				font-weight: %s;
				font-family: '%s',sans-serif!important;
			}
			</style>
HTML
				,
				$font['css'],
				htmlspecialchars_decode( $font['selector'] ),
				$font['style'],
				$font['weight'],
				$font['family']
			);
			$loaded[] = $font['css'];
		}
	}

	/**
	 * 加载排印优化
	 */
	public function load_typography() {
		// code from corner-bracket-lover plugin
		if ( in_array( 'corner', (array) $this->settings['windfonts_typography'] ) ) {
			$this->page_str_replace( 'init', 'str_replace', [
				'n’t',
				'n&rsquo;t'
			] );
			$this->page_str_replace( 'init', 'str_replace', [
				'’s',
				'&rsquo;s'
			] );
			$this->page_str_replace( 'init', 'str_replace', [
				'’m',
				'&rsquo;m'
			] );
			$this->page_str_replace( 'init', 'str_replace', [
				'’re',
				'&rsquo;re'
			] );
			$this->page_str_replace( 'init', 'str_replace', [
				'’ve',
				'&rsquo;ve'
			] );
			$this->page_str_replace( 'init', 'str_replace', [
				'’d',
				'&rsquo;d'
			] );
			$this->page_str_replace( 'init', 'str_replace', [
				'’ll',
				'&rsquo;ll'
			] );
			$this->page_str_replace( 'init', 'str_replace', [
				'“',
				'&#12300;'
			] );
			$this->page_str_replace( 'init', 'str_replace', [
				'”',
				'&#12301;'
			] );
			$this->page_str_replace( 'init', 'str_replace', [
				'‘',
				'&#12302;'
			] );
			$this->page_str_replace( 'init', 'str_replace', [
				'’',
				'&#12303;'
			] );
		}
		// code from space-lover plugin
		if ( in_array( 'space', (array) $this->settings['windfonts_typography'] ) ) {
			$this->page_str_replace( 'template_redirect', 'preg_replace', [
				'~(\p{Han})([a-zA-Z0-9\p{Ps}\p{Pi}])(?![^<]*>)~u',
				'\1 \2'
			] );
			$this->page_str_replace( 'template_redirect', 'preg_replace', [
				'~([a-zA-Z0-9\p{Pe}\p{Pf}])(\p{Han})(?![^<]*>)~u',
				'\1 \2'
			] );
			$this->page_str_replace( 'template_redirect', 'preg_replace', [
				'~([!?‽:;,.%])(\p{Han})~u',
				'\1 \2'
			] );
			$this->page_str_replace( 'template_redirect', 'preg_replace', [
				'~(\p{Han})([@$#])~u',
				'\1 \2'
			] );
			$this->page_str_replace( 'template_redirect', 'preg_replace', [
				'~(&amp;?(?:amp)?;) (\p{Han})(?![^<]*>)~u',
				'\1\2'
			] );
			$this->page_str_replace( 'template_redirect', 'preg_replace', [
				'~(\p{Han})(<(?!ruby)[a-zA-Z]+?[^>]*?>)([a-zA-Z0-9\p{Ps}\p{Pi}@$#])~u',
				'\1 \2\3'
			] );
			$this->page_str_replace( 'template_redirect', 'preg_replace', [
				'~(\p{Han})(<\/(?!ruby)[a-zA-Z]+>)([a-zA-Z0-9])~u',
				'\1\2 \3'
			] );
			$this->page_str_replace( 'template_redirect', 'preg_replace', [
				'~([a-zA-Z0-9\p{Pe}\p{Pf}!?‽:;,.%])(<(?!ruby)[a-zA-Z]+?[^>]*?>)(\p{Han})~u',
				'\1 \2\3'
			] );
			$this->page_str_replace( 'template_redirect', 'preg_replace', [
				'~([a-zA-Z0-9\p{Ps}\p{Pi}!?‽:;,.%])(<\/(?!ruby)[a-zA-Z]+>)(\p{Han})~u',
				'\1\2 \3'
			] );
			$this->page_str_replace( 'template_redirect', 'preg_replace', [
				'~[ ]*([「」『』（）〈〉《》【】〔〕〖〗〘〙〚〛])[ ]*~u',
				'\1'
			] );
		}
		// code from quotmarks-replacer plugin
		if ( in_array( 'punctuation', (array) $this->settings['windfonts_typography'] ) ) {
			$qmr_work_tags = array(
				'the_title',             // http://codex.wordpress.org/Function_Reference/the_title
				'the_content',           // http://codex.wordpress.org/Function_Reference/the_content
				'the_excerpt',           // http://codex.wordpress.org/Function_Reference/the_excerpt
				// 'list_cats',          Deprecated. http://codex.wordpress.org/Function_Reference/list_cats
				'single_post_title',     // http://codex.wordpress.org/Function_Reference/single_post_title
				'comment_author',        // http://codex.wordpress.org/Function_Reference/comment_author
				'comment_text',          // http://codex.wordpress.org/Function_Reference/comment_text
				// 'link_name',          Deprecated.
				// 'link_notes',         Deprecated.
				'link_description',      // Deprecated, but still widely used.
				'bloginfo',              // http://codex.wordpress.org/Function_Reference/bloginfo
				'wp_title',              // http://codex.wordpress.org/Function_Reference/wp_title
				'term_description',      // http://codex.wordpress.org/Function_Reference/term_description
				'category_description',  // http://codex.wordpress.org/Function_Reference/category_description
				'widget_title',          // Used by all widgets in themes
				'widget_text'            // Used by all widgets in themes
			);

			foreach ( $qmr_work_tags as $qmr_work_tag ) {
				remove_filter( $qmr_work_tag, 'wptexturize' );
			}
		}
		
		    // 支持中文排版段首缩进 2em
        if ( in_array( 'indent', (array) $this->settings['windfonts_typography'] ) ) {
        add_action( 'wp_head', function () {
            echo '<style>
            .entry-content p {
                text-indent: 2em;
            }
            .entry-content .wp-block-group p,
            .entry-content .wp-block-columns p,
            .entry-content .wp-block-media-text p,
            .entry-content .wp-block-quote p {
                text-indent: 0;
            }

            </style>';
        } );
     }
		    // 支持中文排版两端对齐
        if ( in_array( 'align', (array) $this->settings['windfonts_typography'] ) ) {
        add_action( 'wp_head', function () {
            echo '<style>
            .entry-content p {
                text-align: justify;
            }
            .entry-content .wp-block-group p,
            .entry-content .wp-block-columns p,
            .entry-content .wp-block-media-text p,
            .entry-content .wp-block-quote p {
                text-align: unset !important; 
            }
           .entry-content .wp-block-columns p.has-text-align-center {
            text-align: center !important;
            }
            </style>';
        } );
     }		
		
	}

	/**
	 * 加载广告拦截
	 */
	public function load_adblock() {
		foreach ( (array) $this->settings['adblock_rule'] as $rule ) {
			if ( empty( $rule['enable'] ) ) {
				continue;
			}
			if ( empty( $rule['selector'] ) ) {
				continue;
			}
			echo sprintf( <<<HTML
			<style>
			%s {
				display: none!important;
			}
			</style>
HTML
				,
				htmlspecialchars_decode( $rule['selector'] )
			);
		}
	}

	/**
	 * 加载飞行模式
	 */
	public function load_plane() {
		add_filter( 'pre_http_request', function ( $preempt, $parsed_args, $url ) {
			foreach ( (array) $this->settings['plane_rule'] as $rule ) {
				if ( empty( $rule['enable'] ) ) {
					continue;
				}
				if ( empty( $rule['url'] ) ) {
					continue;
				}
				if ( strpos( $url, $rule['url'] ) !== false ) {
					return new WP_Error( 'http_request_not_executed', '无用 URL 已屏蔽访问' );
				}
			}

			return $preempt;
		}, PHP_INT_MAX, 3 );
	}

	/**
	 * WordPress.Org 替换
	 */
	public function filter_wordpress_org( $preempt, $args, $url ) {
		if ( $preempt || isset( $args['_wp_china_yes'] ) ) {
			return $preempt;
		}
		if ( ( ! strpos( $url, 'api.wordpress.org' ) && ! strpos( $url, 'downloads.wordpress.org' ) ) ) {
			return $preempt;
		}

		if ( $this->settings['store'] == 'wenpai' ) {
			$url = str_replace( 'api.wordpress.org', 'api.wenpai.net', $url );
		} else {
			$url = str_replace( 'api.wordpress.org', 'api.wpmirror.com', $url );
		}
		$url = str_replace( 'downloads.wordpress.org', 'downloads.wenpai.net', $url );

		$curl_version = '1.0.0';
		if ( function_exists( 'curl_version' ) ) {
			$curl_version_array = curl_version();
			if ( is_array( $curl_version_array ) && key_exists( 'version', $curl_version_array ) ) {
				$curl_version = $curl_version_array['version'];
			}
		}
		if ( version_compare( $curl_version, '7.15.0', '<' ) ) {
			$url = str_replace( 'https://', 'http://', $url );
		}

		$args['_wp_china_yes'] = true;

		return wp_remote_request( $url, $args );
	}

	/**
	 * 初认头像替换
	 */
	public function get_cravatar_url( $url ) {
		switch ( $this->settings['cravatar'] ) {
			case 'cn':
				return $this->replace_avatar_url( $url, 'cn.cravatar.com' );
			case 'global':
				return $this->replace_avatar_url( $url, 'en.cravatar.com' );
			case 'weavatar':
				return $this->replace_avatar_url( $url, 'weavatar.com' );
			default:
				return $url;
		}
	}

	/**
	 * 头像 URL 替换
	 */
	public function replace_avatar_url( $url, $domain ) {
		$sources = array(
			'www.gravatar.com',
			'0.gravatar.com',
			'1.gravatar.com',
			'2.gravatar.com',
			's.gravatar.com',
			'secure.gravatar.com',
			'cn.gravatar.com',
			'en.gravatar.com',
			'gravatar.com',
			'sdn.geekzu.org',
			'gravatar.duoshuo.com',
			'gravatar.loli.net',
			'dn-qiniu-avatar.qbox.me'
		);

		return str_replace( $sources, $domain, $url );
	}

	/**
	 * WordPress 讨论设置中的默认 LOGO 名称替换
	 */
	public function set_defaults_for_cravatar( $avatar_defaults ) {
		if ( $this->settings['cravatar'] == 'weavatar' ) {
			$avatar_defaults['gravatar_default'] = 'WeAvatar';
		} else {
			$avatar_defaults['gravatar_default'] = '初认头像';
		}

		return $avatar_defaults;
	}

	/**
	 * 个人资料卡中的头像上传地址替换
	 */
	public function set_user_profile_picture_for_cravatar() {
		if ( $this->settings['cravatar'] == 'weavatar' ) {
			return '<a href="https://weavatar.com" target="_blank">您可以在 WeAvatar 修改您的资料图片</a>';
		} else {
			return '<a href="https://cravatar.com" target="_blank">您可以在初认头像修改您的资料图片</a>';
		}
	}

	/**
	 * 页面替换
	 *
	 * @param $replace_func string 要调用的字符串关键字替换函数
	 * @param $param array 传递给字符串替换函数的参数
	 */
	private function page_str_replace( $hook, $replace_func, $param ) {
		// CLI 下返回，防止影响缓冲区
		if ( php_sapi_name() == 'cli' ) {
			return;
		}
		add_action( $hook, function () use ( $replace_func, $param ) {
			ob_start( function ( $buffer ) use ( $replace_func, $param ) {
				$param[] = $buffer;

				return call_user_func_array( $replace_func, $param );
			} );
		}, PHP_INT_MAX );
	}
}
