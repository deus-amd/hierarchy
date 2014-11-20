<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}


/**
 * Hierarchy_Table
 * Display Hierarchy in a WP_List_table
 **/
class Hierarchy_Table extends WP_List_Table {

	private $url;

	private $post_types = array();
	private $settings = array();

	function __construct() {
		parent::__construct( array(
			'singular'  => 'hierarchyentry',
			'plural'    => 'hierarchyentries',
			'ajax'      => false
		) );
	}

	public function set_url( $url ) {
		$this->url = esc_url( $url );
	}

	public function set_post_types( $post_types ) {
		$this->post_types = $post_types;
	}

	public function set_settings( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Default column handler if there's no specific handler
	 *
	 * @param $item
	 * @param $column_name
	 * @return mixed
	 */
	function column_default( $item, $column_name ) {
		$item = $item['entry'];

		switch( $column_name ) {
			case 'title':
			case 'author':
			case 'comments':
			case 'date':
			case 'icon':
				return $item[$column_name];
			default:
				return print_r( $item, true ); // worst case, output for debugging
		}
	}


	/**
	 * Define the columns we plan on using
	 *
	 * @return array
	 */
	function get_columns() {
		$columns = array(
			'icon'      => '',
			'title'     => __( 'Title', 'hierarchy' ),
			'author'    => __( 'Author', 'hierarchy' ),
			'comments'  => '<span><span class="vers"><img src="' . get_admin_url() . 'images/comment-grey-bubble.png" alt="Comments" /></span></span>',
			'date'      => __( 'Date', 'hierarchy' )
		);

		return $columns;
    }

	/**
	 * Get the Dashicon for a post type
	 *
	 * @since 0.6
	 * @param $post_type
	 * @return string
	 */
	function get_post_type_icon( $post_type ) {
		$icon = '<span class="dashicons dashicons-admin-post"></span>';

		switch ( $post_type->name ) {
			case 'page':
				$icon = '<span class="dashicons dashicons-admin-page"></span>';
				break;
			default: // custom post type
				if ( false !== strpos( $post_type->menu_icon, 'dashicons' ) )  {
					$icon = '<span class="dashicons ' . esc_attr( $post_type->menu_icon ) . '"></span>';
				}
				break;
		}

		return $icon;
	}


    /**
     * Handle the Icon column
     *
     * @param $item
     * @return string  HTML for Dashicon for post type
     */
    function column_icon( $item ) {

	    if ( ! isset( $item['post_type'] ) ) {
		    $item['post_type'] = 'post';
		}

	    $post_type = get_post_type_object( $item['post_type'] );
		$icon = $this->get_post_type_icon( $post_type );

        return $icon;
    }


    /**
     * Handle the Title column
     *
     * @param $item
     * @return string   Proper title for the column
     */
    function column_title( $item ) {
        // build row actions
        $actions        = array();
        $item           = $item['entry'];
        $title          = $item['title'];

        $count          = 0;
        $count_label    = '';
	    $post_type      = '';

        // we need to make this contextual as per the content type
        if( is_int( $item['ID'] ) )
        {
            // it's an actual post
            $edit_url = get_admin_url() . 'post.php?post=' . $item['ID'] . '&action=edit';

            $actions['edit'] = '<a href="' . $edit_url . '">Edit</a>';
            $actions['view'] = '<span class="view"><a href="' . get_bloginfo( 'url' ) . '/?page_id=' . $item['ID'] . '" rel="permalink">View</a></span>';
        }
        else
        {
            // it's a CPT index
            $cpt = null;
            foreach( $this->post_types as $post_type ) {
                if( $post_type == $item['ID'] )  {
                    $cpt = get_post_type_object( $post_type );
                    break;
                }
            }

	        $post_type = $cpt->name;

            $title = $item['pad'] . $cpt->labels->name;

            $posts_page = ( 'page' == get_option( 'show_on_front' ) ) ? intval( get_option( 'page_for_posts' ) ) : false;

            // set the Posts label to be the posts page Title
            if( $cpt->name == 'post' && !empty( $posts_page ) )
            {
                $title = $item['pad'] . get_the_title( $posts_page );
            }

            $title .= ' &raquo;';

            $edit_url = get_admin_url() . 'edit.php?post_type=' . $cpt->name;

            $actions['edit'] = '<a href="' . $edit_url . '">Edit</a>';

            // set entry count
            $counts = wp_count_posts( $cpt->name );

            // we've got counts broken out by status, so let's get a comprehensive number
            if( isset( $counts->publish ) ) $count += (int) $counts->publish;
            if( isset( $counts->future ) )  $count += (int) $counts->future;
            if( isset( $counts->draft ) )   $count += (int) $counts->draft;
            if( isset( $counts->pending ) ) $count += (int) $counts->pending;
            if( isset( $counts->private ) ) $count += (int) $counts->private;
            if( isset( $counts->inherit ) ) $count += (int) $counts->inherit;

            $count_label .= ' (' . $count . ' ';
            $count_label .= ( $count == 1 ) ? __( $cpt->labels->singular_name, 'hierarchy' ) : __( $cpt->labels->name, 'hierarchy' );
            $count_label .= ') ';

            // let's check to see if we in fact have a CPT archive to use for the View link
            if( $cpt->has_archive || $cpt->name == 'post' && $posts_page )
            {
                if( $cpt->name == 'post' && $posts_page )
                {
                    $actions['view'] = '<a href="' . get_permalink( $posts_page) . '">View</a>';
                }
                else
                {
                    $actions['view'] = '<a href="' . get_post_type_archive_link( $cpt->name ) . '">View</a>';
                }
            }

	        if ( empty( $this->settings['post_types'][ $post_type ]['no_new'] ) ) {
                $add_url = get_admin_url() . 'post-new.php?post_type=' . $cpt->name;
                $actions['add'] = '<a href="' . $add_url . '">Add New</a>';
	        }

            // let's see if we need to add any taxonomies
	        $args       = array(
		        'public'        => true,
		        'object_type'   => array( $cpt->name )
	        );
	        $output     = 'objects';
	        $operator   = 'and';
	        $taxonomies = get_taxonomies( $args, $output, $operator );

            if( !empty( $taxonomies ) )
            {
                foreach( $taxonomies as $taxonomy )
                {
                    if( $taxonomy->name != 'post_format' )
                    {
                        $tax_edit_url = get_admin_url() . 'edit-tags.php?taxonomy=' . $taxonomy->name;
                        if( $cpt->name != 'post' )
                        {
                            $tax_edit_url .= '&post_type=' . $cpt->name;
                        }
                        $actions['tax_' . $taxonomy->name] = '<a href="' . $tax_edit_url . '">' . $taxonomy->labels->name . '</a>';
                    }
                }
            }
        }

        // return the title contents
        $final_title = '<strong><a class="row-title" href="' . $edit_url . '">' . $title . '</a></strong>';
        if( $count ) $final_title .= $count_label;
        $final_title .= $this->row_actions( $actions );

	    $final_markup = '';
	    if ( ! empty( $post_type ) ) {
		    $final_markup .= '<div class="hierarchy-row-post-type hierarchy-row-post-type-' . $post_type . '">';
	    }

	    $final_markup .= $final_title;

	    if ( ! empty( $post_type ) ) {
		    $final_markup .= '</div>';
	    }

        return  $final_markup;
    }


    /**
     * Handle the Comments column
     *
     * @param $item
     * @return string
     */
    function column_comments( $item ) {
        $item = $item['entry'];
        $column = '';
        if ( is_numeric( $item['ID'] ) ) {
            $column = '<div class="post-com-count-wrapper"><a class="post-com-count" style="cursor:default;"><span class="comment-count">' . $item['comments'] . '</span></a></div>';
        }

        return $column;
    }


    /**
     * Preps the data for display in the table
     *
     * @param array $cpts
     */
    function prepare_items( $hierarchy = null )
    {
        // pagination
        if( defined( 'HIERARCHY_PREFIX' ) )
        {
            $settings = get_option( HIERARCHY_PREFIX . 'settings' );
            $per_page = intval( $settings['per_page'] );
        }
        else
        {
            $per_page = 100;
        }

        // define our column headers
        $columns                = $this->get_columns();
        $hidden                 = array();
        $sortable               = $this->get_sortable_columns();
        $this->_column_headers  = array( $columns, $hidden, $sortable ); // actually set the data

        // define our data to be shown
        $data = $hierarchy;

        // find out what page we're currently on and get pagination set up
        $current_page   = $this->get_pagenum();
        $total_items    = count( $data );

        if( $per_page > 0 )
        {
            // if we do want pagination, we'll just split our array
            // the class doesn't handle pagination, so we need to trim the data to only the page we're viewing
            $data = array_slice( $data, ( ( $current_page - 1 ) * $per_page ), $per_page );
        }

        // our data has been prepped (i.e. sorted) and we can now use it
        $this->items = $data;

        if( $per_page > 0 )
        {
            // register our pagination options and calculations
            $this->set_pagination_args( array(
                    'total_items' => $total_items,
                    'per_page'    => $per_page,
                    'total_pages' => ceil( $total_items / $per_page ),
                )
            );
        }

    }
}
