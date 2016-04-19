<?php
/**
 * This file implements the View Count plugin for b2evolution
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/gnu-gpl-license}
 * @copyright (c)2003-2016 by Francois Planque - {@link http://fplanque.com/}
 *
 * @package plugins
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * The View Count plugin
 */
class view_count_plugin extends Plugin
{
	var $name;
	var $code = 'evo_viewcount';
	var $priority = 10;
	var $version = '5.0.0';
	var $author = 'The b2evo Group';
	var $group = 'widget';
	var $subgroup = 'other';

	var $item_views_data;


	/**
	 * Init
	 */
	function PluginInit( & $params )
	{
		$this->name = T_('View Count Widget');
		$this->short_desc = T_('Count each display of disp=single for each item.');
		$this->long_desc = T_('Count each display of disp=single for each item.');
	}


	/**
	 * Get definitions for widget specific editable params
	 *
	 * @see Plugin::GetDefaultSettings()
	 * @param local params like 'for_editing' => true
	 */
	function get_widget_param_definitions( $params )
	{
		$r = parent::get_widget_param_definitions( $params );

		if( isset( $r['allow_blockcache'] ) )
		{	// Disable "allow blockcache" because this widget displays dynamic data:
			$r['allow_blockcache']['defaultvalue'] = false;
			$r['allow_blockcache']['disabled'] = 'disabled';
			$r['allow_blockcache']['note'] = T_('This widget cannot be cached in the block cache.');
		}

		return $r;
	}


	/**
	 * We want a table to store a count of views for each Item
	 *
	 * @return array
	 */
	function GetDbLayout()
	{
		return array(
				'CREATE TABLE '.$this->get_sql_table( 'items' ).' (
					vcip_itm_ID INT(11) UNSIGNED NOT NULL,
					vcip_count  INT UNSIGNED NOT NULL DEFAULT 0,
					vcip_date   TIMESTAMP NOT NULL DEFAULT "2000-01-01 00:00:00",
					PRIMARY KEY( vcip_itm_ID )
				) ENGINE = innodb',
			);
	}


	/**
	 * Event handler: Called at the end of the skin's HTML BODY section.
	 *
	 * Use this to add any HTML snippet at the end of the generated page.
	 */
	function SkinEndHtmlBody( & $params )
	{
		global $DB, $localtimenow;

		if( $Item = & $this->get_viewed_Item() )
		{
			$DB->begin( 'SERIALIZABLE' );

			$item_views_data = $this->get_item_views_data( $Item->ID, true );
			if( empty( $item_views_data ) )
			{	// Insert new record for the Item:
				$DB->query( 'INSERT INTO '.$this->get_sql_table( 'items' ).'
					       ( vcip_itm_ID, vcip_count, vcip_date )
					VALUES ( '.$DB->quote( $Item->ID ).', 1, '.$DB->quote( date2mysql( $localtimenow ) ).' )',
					'Insert new record to count the views for the Item #'.$Item->ID );

				// Store the views data in cache:
				$this->item_views_data[ $Item->ID ] = array(
						'count' => 1,
						'date'  => date2mysql( $localtimenow ),
					);
			}
			else
			{	// Increase a count for the Item:
				$DB->query( 'UPDATE '.$this->get_sql_table( 'items' ).'
					  SET vcip_count = vcip_count + 1
					WHERE vcip_itm_ID = '.$DB->quote( $Item->ID ),
					'Increase a count the views for the Item #'.$Item->ID );

				// Update the cache:
				$this->item_views_data[ $Item->ID ]['count'] = $item_views_data['count'] + 1;
			}

			$DB->commit();
		}
	}


	/**
	 * Event handler: SkinTag (widget)
	 *
	 * @param array Associative array of parameters.
	 * @return boolean did we display?
	 */
	function SkinTag( & $params )
	{
		if( ! ( $Item = & $this->get_viewed_Item() ) )
		{	// No viewed Item:
			return false;
		}

		// Get views data of the viewed Item:
		$item_views_data = $this->get_item_views_data( $Item->ID );

		if( empty( $item_views_data ) )
		{	// No views yet:
			return false;
		}

		echo $params['block_start'];

		echo $params['block_body_start'];

		printf( T_('This post has been viewed %d times since %s'), $item_views_data['count'], mysql2localedate( $item_views_data['date'] ) );

		echo $params['block_body_end'];

		echo $params['block_end'];

		return true;
	}


	/**
	 * Get Item object that is currently viewed on disp=single or disp=page
	 *
	 * @return object Item
	 */
	function & get_viewed_Item()
	{
		global $disp, $MainList;

		if( ! empty( $disp ) && ( $disp == 'single' || $disp == 'page' ) &&
		    ! empty( $MainList ) && $MainList->single_post )
		{	// If disp=single or disp=page:

			// Restart list to get first single Item:
			$MainList->restart();

			$single_Item = & mainlist_get_item();

			return $single_Item;
		}

		$r = false;
		return $r;
	}


	/**
	 * Get views data of the Item
	 *
	 * @param integer Item ID
	 * @param boolean TRUE - Force to get a result from DB even if it is already in cache
	 * @return array Item views data. Array keys: 'count', 'date'
	 */
	function get_item_views_data( $item_ID, $force = false )
	{
		global $DB;

		if( ! is_array( $this->item_views_data ) )
		{	// Initialize cache array first time:
			$this->item_views_data = array();
		}

		if( $force || ! isset( $this->item_views_data[ $item_ID ] ) )
		{	// Get views data for the Item and save this in cache:
			$SQL = new SQL( 'Get views count of the Item #'.$item_ID.' by plugin '.$this->code.' #'.$this->ID.( $force ? ' (FORCED)' : '' ) );
			$SQL->SELECT( 'vcip_count AS `count`, vcip_date AS `date`' );
			$SQL->FROM( $this->get_sql_table( 'items' ) );
			$SQL->WHERE( 'vcip_itm_ID = '.$DB->quote( $item_ID ) );

			$this->item_views_data[ $item_ID ] = $DB->get_row( $SQL->get(), ARRAY_A, NULL, $SQL->title );
		}

		return $this->item_views_data[ $item_ID ];
	}
}

?>