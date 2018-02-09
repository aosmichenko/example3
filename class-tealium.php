<?php
/**
 * Adds the Tealium tag and creates a data layer for your WordPress site.
 * Developer: aosmichenko
 * @package AMI\Shared
 */

namespace AMI\Shared;

/**
 * Tealum class.
 */
class Tealium {
	/**
	 * Load method.
	 * @return void
	 */
	public static function load() {
		new Tealium();
	}

	/**
	 * Constructor.
	 * @return void
	 */
	public function __construct() {
		add_filter( 'ami_utag_data', array( $this, 'add_single_data' ) );
		add_filter( 'ami_utag_data', array( $this, 'add_singular_data' ) );
		add_filter( 'ami_utag_data', array( $this, 'add_archive_data' ) );
		add_action( 'wp_head', array( $this, 'utag_data' ), 1 );
		add_action( 'after_bodytag', array( $this, 'tealium_tag' ) ); // This action need to be created right after opening <body> tag
	}

	/**
	 * Inserts the Tealium tag.
	 * @return void
	 */
	public function tealium_tag() {
		$tiq_cdn             = 'tags.tiqcdn.com';
		$tealium_account     = 'ami';
		$tealium_profile     = $this->get_profile();
		$tealium_environment = $this->get_env();
		$tealium_tag_type    = apply_filters( 'ami_tealium_tag_type', 0 );
		$cache_buster        = ( 'dev' === $tealium_environment || 'qa' === $tealium_environment ) ? '?_cb=' . time() : '';
		$src                 = ( is_ssl() ) ? 'https://' : 'http://';
		$src                 .= "{$tiq_cdn}/utag/{$tealium_account}/{$tealium_profile}/{$tealium_environment}/utag.js{$cache_buster}";
		if ( '1' !== $tealium_tag_type ) {
			$tealiumtag = "<!-- Loading script asynchronously -->\n";
			$tealiumtag .= "<script type=\"text/javascript\">\n";
			$tealiumtag .= " (function(a,b,c,d){\n";
			$tealiumtag .= " a='" . esc_url( $src ) . "';\n";
			$tealiumtag .= " b=document;c='script';d=b.createElement(c);d.src=a;d.type='text/java'+c;d.async=true;\n";
			$tealiumtag .= " a=b.getElementsByTagName(c)[0];a.parentNode.insertBefore(d,a);\n";
			$tealiumtag .= " })();\n";
			$tealiumtag .= "</script>\n";
			$tealiumtag .= "<!-- END: T-WP -->\n";
		} else {
			$tealiumtag = "<!-- Loading script synchronously -->\n";
			$tealiumtag .= '<script type="text/javascript" src="' . esc_url( $src ) . '"></script>' . "\n"; // @codingStandardsIgnoreLine
			$tealiumtag .= "<!-- END: T-WP -->\n";
		}
		echo $tealiumtag;// WPCS: XSS ok.
	}

	/**
	 * Gets the Tealium profile.
	 * @return string
	 */
	private function get_profile() {
		if ( defined( 'AMI_TEALIUM_PROFILE' ) ) {
			return AMI_TEALIUM_PROFILE;
		}
		$home = home_url();
		if ( strpos( $home, 'radaronline.com' ) ) {
			$name = 'radaronline';
		} elseif ( strpos( $home, 'okmagazine.com' ) ) {
			$name = 'okmagazine';
		} elseif ( strpos( $home, 'starmagazine.com' ) ) {
			$name = 'starmagazine';
		} elseif ( strpos( $home, 'nationalenquirer.com' ) ) {
			$name = 'nationalenquirer';
		} elseif ( strpos( $home, 'soapoperadigest.com' ) ) {
			$name = 'soapoperadigest';
		} else {
			$name = 'ami';
		}
		return apply_filters( 'ami_tealium_profile_name', $name );
	}

	/**
	 * Gets the environment.
	 * @return string
	 */
	private function get_env() {
		if ( defined( 'AMI_TEALIUM_ENV' ) ) {
			return AMI_TEALIUM_ENV;
		}
		$home = home_url();
		if ( strpos( $home, '//dev' ) || strpos( $home, '//loc' ) ) {
			$env = 'dev';
		} elseif ( strpos( $home, '//qa' ) ) {
			$env = 'qa';
		} else {
			$env = 'prod';
		}
		return apply_filters( 'ami_tealium_env', $env );
	}

	/**
	 * Displays the utag data.
	 * @return void
	 */
	public function utag_data() {
		if ( is_page_template( 'noads-page.php' ) ) {
			return;
		}
		$utag_data = array(
			'canonical_url' => $this->get_canonical(),
			'page_category' => $this->get_page_category(),
		);
		if ( is_home() || is_front_page() ) {
			$page                     = get_query_var( 'paged', 1 );
			$utag_data['page_number'] = ( 0 === $page ) ? '1' : (string) $page;
		}
		$utag_data = apply_filters( 'ami_utag_data', $utag_data );
		echo '<script>';
		echo 'var utag_data = ' . wp_json_encode( $utag_data ) . ";\n";
		echo $this->helper_js(); // WPCS: XSS ok.
		echo $this->get_screen_size(); // WPCS: XSS ok.
		echo $this->get_utm_params(); // WPCS: XSS ok.
		echo '
			if(parseUrlParams("test") !== "none") {
				console.log(utag_data);
			}';
		echo 'function tealiumOnLoad() {
			utag_data.event_name = \'onLoad\';
			utag.view(utag_data);
			if(parseUrlParams("test") !== "none") {
				console.log("tealium onload");
			}
			}';
		echo 'window.addEventListener("load", tealiumOnLoad);';
		echo '</script>';
	}

	/**
	 * Gets the canonical URL.
	 * @return string
	 */
	private function get_canonical() {
		$canonical = '';
		$canonical = apply_filters( 'ami_tealium_canonical_before', $canonical );
		if ( empty( $canonical ) ) {
			global $wp;
			$canonical = '/' . $wp->request . '/';
		}
		return apply_filters( 'ami_tealium_canonical_after', $canonical );
	}

	/**
	 * Gets the page category.
	 * @return string
	 */
	private function get_page_category() {
		$pc = '';
		$pc = apply_filters( 'ami_tealium_page_category_before', $pc );
		if ( empty( $pc ) ) {
			if ( is_front_page() || is_home() ) {
				$pc = 'home';
			} elseif ( is_archive() ) {
				if ( is_category() ) {
					$pc = 'category';
				} elseif ( is_tag() ) {
					$pc = 'tag';
				} elseif ( is_author() ) {
					$pc = 'author';
				} elseif ( is_date() ) {
					$pc = 'date';
				} elseif ( is_tax( 'actors' ) ) {
					$pc = 'actor';
				} elseif ( is_tax( 'shows' ) ) {
					$pc = 'show';
				} elseif ( is_tax( 'post_format', 'post-format-gallery' ) ) {
					$pc = 'galleryPage';
				} elseif ( is_tax( 'post_format', 'post-format-video' ) ) {
					$pc = 'videoPage';
				} else {
					$pc = 'archive';
				}
			} elseif ( is_search() || is_page( 'search' ) ) {
				$pc = 'search_results';
			} elseif ( is_page() ) {
				if ( is_page_template( 'page-products.php' ) ) {
					$pc = 'productList';
				} elseif ( is_page_template( 'page-events.php' ) || is_page_template( 'page-shows.php' ) || is_page_template( 'page-actors.php' ) ) {
					$pc = 'landing';
				} else {
					$pc = 'page';
				}
			} elseif ( is_404() ) {
				$pc = '404';
			} elseif ( is_singular( 'post' ) ) {
				$pc = 'post';
			} elseif ( is_singular( 'product-page' ) ) {
				$pc = 'product';
			} elseif ( is_singular( 'sweep' ) ) {
				$pc = 'sweeps';
			} elseif ( is_singular( 'event' ) ) {
				$pc = 'event';
			} else {
				$pc = 'none';
			} // End if().
		} // End if().
		return apply_filters( 'ami_tealium_page_category_after', $pc );
	}

	/**
	 * Gets UTM params.
	 * @return string
	 */
	private function get_utm_params() {
		return "
		utag_data.referrer = document.referrer;
		utag_data.utm_source = parseUrlParams('utm_source');
		utag_data.utm_medium = parseUrlParams('utm_medium');
		utag_data.utm_campaign = parseUrlParams('utm_campaign');
		";
	}

	/**
	 * Gets the screen size JS.
	 * @return string
	 */
	private function get_screen_size() {
		return "
		if(window.innerWidth < 768) {
			utag_data.site_display_format = 'mobile';
		} else if(window.innerWidth >= 768 && window.innerWidth <= 1024) {
			utag_data.site_display_format = 'tablet';
		} else {
			utag_data.site_display_format = 'desktop';
		}
		";
	}

	/**
	 * Adds single data to utag.
	 * @param array $utag_data Utag data.
	 * @return array
	 */
	public function add_single_data( $utag_data ) {
		if ( defined( 'AMI_TEALIUM_SINGLE_DATA_OFF' ) ) {
			return $utag_data;
		}
		$post = get_post();
		if ( is_singular( 'post' ) ) {
			$utag_data                         = $this->get_post_meta( $utag_data );
			$utag_data['node_id']              = (string) $this->get_id( $post );
			$utag_data['content_type']         = $this->get_content_type( $post );
			$utag_data['node_category']        = $this->get_categories( $post );
			$utag_data['node_tags']            = $this->get_tags( $post );
			$utag_data['content_updated_date'] = get_the_modified_date( 'Y/m/d' );
			$utag_data['content_publish_date'] = get_the_time( 'Y/m/d', $post );
			$utag_data['page_title']           = get_the_title( $post );
			$utag_data['sponsorship_name']     = $this->get_sponsorship( $post );
			if ( has_post_format( 'gallery', $post->ID ) ) {
				$utag_data['gallery_slides'] = (string) get_post_meta( $post->ID, 'media_count', true );
			}
		} elseif ( is_singular( 'product-page' ) ) {
			$utag_data = $this->get_post_meta( $utag_data );
		}
		if ( is_singular() && comments_open( $post ) ) {
			$utag_data['comments'] = 'on';
		} else {
			$utag_data['comments'] = 'off';
		}
		return apply_filters( 'ami_tealium_single_data_after', $utag_data );
	}

	/**
	 * Gets an ID.
	 * @param mixed $post The post.
	 * @return int
	 */
	private function get_id( $post = false ) {
		$t_id = '';
		$t_id = apply_filters( 'ami_tealium_get_id_before', $t_id );
		if ( empty( $t_id ) ) {
			if ( is_singular() ) {
				if ( false === $post ) {
					$post = get_post();
				}
				$t_id = $post->ID;
			} elseif ( is_tag() ) {
				$tagid = get_query_var( 'tag_id' );
				$t_id  = ( $tagid ) ? $tagid : 0;
			} elseif ( is_category() ) {
				$catid = get_query_var( 'cat' );
				$t_id  = ( $catid ) ? $catid : 0;
			} elseif ( is_tax( 'actors' ) ) {
				$tag_id   = get_query_var( 'actors' );
				if ( function_exists( 'ami_get_term_by' ) ) {
					$term_obj = ami_get_term_by( 'slug', $tag_id, 'actors' );
				} elseif ( function_exists( 'wpcom_vip_get_term_by' ) ) {
					$term_obj = wpcom_vip_get_term_by( 'slug', $tag_id, 'actors' );
				} else {
					// @codingStandardsIgnoreLine
					$term_obj = get_term_by( 'slug', $tag_id, 'actors' );
				}
				$termid   = 0;
				if ( is_object( $term_obj ) && ! is_wp_error( $term_obj ) ) {
					$termid = $term_obj->term_id;
				}
				$t_id = ( $termid ) ? $termid : 0;
			} elseif ( is_tax( 'shows' ) ) {
				$tag_id   = get_query_var( 'shows' );
				if ( function_exists( 'ami_get_term_by' ) ) {
					$term_obj = ami_get_term_by( 'slug', $tag_id, 'shows' );
				} elseif ( function_exists( 'wpcom_vip_get_term_by' ) ) {
					$term_obj = wpcom_vip_get_term_by( 'slug', $tag_id, 'shows' );
				} else {
					// @codingStandardsIgnoreLine
					$term_obj = get_term_by( 'slug', $tag_id, 'shows' );
				}
				$termid   = 0;
				if ( is_object( $term_obj ) && ! is_wp_error( $term_obj ) ) {
					$termid = $term_obj->term_id;
				}
				$t_id = ( $termid ) ? $termid : 0;
			} // End if().
		} // End if().
		return apply_filters( 'ami_tealium_get_id_after', $t_id );
	}

	/**
	 * Gets the post meta.
	 * @param array $utag_data Utag data.
	 * @return array
	 */
	private function get_post_meta( $utag_data ) {
		if ( defined( 'AMI_TEALIUM_POST_META_OFF' ) ) {
			return $utag_data;
		}
		$post            = get_post();
		$allowed_vendors = array(
			'taboola'          => '3rd_taboola',
			'swoop'            => '3rd_swoop',
			'zergnet_timeline' => '3rd_zergnet_timeline',
			'vuble'            => '3rd_vuble',
		);
		$allowed_meta = array(
			'sponsored'       => 'standout_sponsored',
			'instant_article' => 'standout_fb',
			'vupulse'         => 'standout_vupulse',
			'wibbitz'         => 'wibbitz_video_id',
		);
		foreach ( $allowed_meta as $k => $v ) {
			$val             = get_post_meta( $post->ID, $v, true );
			$utag_data[ $k ] = ( $val ) ? 'on' : 'off';
		}
		$global_off = get_post_meta( $post->ID, '3rd_all', true );
		foreach ( $allowed_vendors as $k => $v ) {
			$val             = get_post_meta( $post->ID, $v, true );
			$utag_data[ $k ] = ( $val ) ? 'off' : 'on';
			if ( $global_off ) {
				$utag_data[ $k ] = 'off';
			}
		}
		if ( has_term( null, 'sponsorship' ) ) {
			$utag_data['sponsored'] = 'on';
		}
		if ( has_post_format( 'video', $post ) ) {
			$jw    = get_post_meta( $post->ID, '_radar_jw_video_id', true );
			$aol   = get_post_meta( $post->ID, '_radar_aol_metabox_id', true );
			$yt    = get_post_meta( $post->ID, 'radar_youtube_id', true );
			$vimeo = get_post_meta( $post->ID, 'radar_vimeo_id', true );
			$hulu  = get_post_meta( $post->ID, '_radar_hulu_metabox_id', true );
			$utag_data['jwplayer_video'] = ( $jw ) ? 'on' : 'off';
			$utag_data['aol_on_video']   = ( $aol ) ? 'on' : 'off';
			$utag_data['youtube_video']  = ( $yt ) ? 'on' : 'off';
			$utag_data['vimeo_video']    = ( $vimeo ) ? 'on' : 'off';
			$utag_data['hulu_video']     = ( $hulu ) ? 'on' : 'off';
		}
		return apply_filters( 'ami_tealium_post_meta_after', $utag_data );
	}

	/**
	 * Gets the post content type.
	 * @param WP_Post $post The post object.
	 * @return string
	 */
	private function get_content_type( $post ) {
		$ct = get_post_format( $post->ID );
		if ( empty( $ct ) ) {
			$ct = 'article';
		} elseif ( 'aside' === $ct ) {
			$ct = 'recipe';
		}
		return $ct;
	}

	/**
	 * Get post categories.
	 * @param WP_Post $post Deprecated.
	 * @return string
	 */
	private function get_categories( $post ) {
		$categories = get_the_category();
		$catout     = 'none';
		if ( $categories && ! is_wp_error( $categories ) ) {
			$catout = array();
			foreach ( $categories as $category ) {
				$catout[] = $category->name;
			}
		}
		return $catout;
	}

	/**
	 * Gets the post sponsorship.
	 * @param WP_Post $post The post object.
	 * @return string
	 */
	private function get_sponsorship( $post ) {
		// TODO: Change this to our sponsor tax?
		$sponsorship = get_the_terms( $post, 'sponsorship' );
		$sponsout    = 'none';
		if ( $sponsorship && ! is_wp_error( $sponsorship ) ) {
			$sponsout = $sponsorship[0]->name;
		}
		return $sponsout;
	}

	/**
	 * Gets the post tags.
	 * @param WP_Post $post Deprecated.
	 * @return array
	 */
	private function get_tags( $post ) {
		$tags   = get_the_tags();
		$tagout = array();
		if ( $tags && ! is_wp_error( $tags ) ) {
			foreach ( $tags as $tag ) {
				$tagout[] = $tag->name;
			}
		}
		return $tagout;
	}

	/**
	 * Adds singular display data.
	 * @param array $utag_data Utag data.
	 * @return array
	 */
	public function add_singular_data( $utag_data ) {
		if ( defined( 'AMI_TEALIUM_SINGULAR_DATA_OFF' ) ) {
			return $utag_data;
		}
		$post = get_post();
		if ( is_singular() ) {
			$utag_data['node_id']              = (string) $this->get_id( $post );
			$utag_data['page_title']           = get_the_title( $post );
			$utag_data['content_updated_date'] = get_the_modified_date( 'Y/m/d' );
			$utag_data['content_publish_date'] = get_the_time( 'Y/m/d', $post );
			$utag_data['sponsorship_name']     = $this->get_sponsorship( $post );
			if ( has_term( null, 'sponsorship' ) ) {
				$utag_data['sponsored'] = 'on';
			}
			if ( is_singular( 'sweep' ) ) {
				$deactivate = get_post_meta( $post->ID, 'deactivation_date', true );
				if ( $deactivate ) {
					$utag_data['content_deactivation_date'] = date( 'Y/m/d', mktime( 0, 0, 0, substr( $deactivate, 4, 2 ), substr( $deactivate, - 2 ), substr( $deactivate, 0, 4 ) ) );
				}
				$utag_data['sponsored']     = 'on';
				$utag_data['content_type']  = 'sweeps';
				$utag_data['node_category'] = $this->get_categories( $post );
			} elseif ( is_singular( 'product-page' ) ) {
				$utag_data['content_type'] = 'product';
				$utag_data['sponsored']    = 'on';
			} elseif ( is_singular( 'event' ) ) {
				$utag_data['content_type'] = 'event';
				unset( $utag_data['sponsorship_name'] );
			}
		}
		if ( is_singular( 'page' ) && ! is_page( 'search' ) ) {
			$utag_data['page_id'] = (string) $this->get_id( $post );
			unset( $utag_data['node_id'] );
		}
		if ( is_page_template( 'page-events.php' ) || is_page_template( 'page-shows.php' ) || is_page_template( 'page-actors.php' ) ) {
			$utag_data['page_title'] = get_the_title( $post );
			unset( $utag_data['page_id'] );
		}
		return apply_filters( 'ami_tealium_singular_data_after', $utag_data );
	}

	/**
	 * Adds archive data.
	 * @param array $utag_data Utag data.
	 * @return array
	 */
	public function add_archive_data( $utag_data ) {
		if ( defined( 'AMI_TEALIUM_ARCHIVE_DATA_OFF' ) ) {
			return $utag_data;
		}
		if ( is_archive() ) {
			$archive_id = $this->get_id();
			if ( $archive_id ) {
				$utag_data['page_id'] = (string) $this->get_id();
			}
			$utag_data['page_title']  = single_term_title( '', false );
			$page                     = get_query_var( 'paged', 1 );
			$utag_data['page_number'] = ( 0 === $page ) ? '1' : (string) $page;
			if ( is_tax( 'actors' ) ) {
				$utag_data['content_type'] = 'actor';
				$utag_data['node_id']      = (string) $this->get_id();
				unset( $utag_data['page_id'] );
			} elseif ( is_tax( 'shows' ) ) {
				$dayshow                   = get_term_meta( $utag_data['page_id'], 'daytime-show', true );
				$active                    = get_term_meta( $utag_data['page_id'], 'active-show', true );
				$utag_data['active']       = ( $active ) ? 'on' : 'off';
				$utag_data['daytime']      = ( $dayshow ) ? 'on' : 'off';
				$utag_data['content_type'] = 'show';
				$utag_data['node_id']      = (string) $this->get_id();
				unset( $utag_data['page_id'] );
			}
		}
		return apply_filters( 'ami_tealium_archive_data_after', $utag_data );
	}

	/**
	 * Displays helper JS.
	 * @return string
	 */
	private function helper_js() {
		return 'function parseUrlParams(val) {
					var result = "none",
						tmp = [];
					var items = location.search.substr(1).split("&");
					for (var index = 0; index < items.length; index++) {
						tmp = items[index].split("=");
						if (tmp[0] === val) result = decodeURIComponent(tmp[1]);
					}
					return result;
				}';
	}
}
