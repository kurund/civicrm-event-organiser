<?php /*
--------------------------------------------------------------------------------
CiviCRM_WP_Event_Organiser_CiviCRM Class
--------------------------------------------------------------------------------
*/

class CiviCRM_WP_Event_Organiser_CiviCRM {

	/**
	 * Properties
	 */

	// parent object
	public $plugin;

	// flag for overriding sync process
	public $do_not_sync = false;



	/**
	 * Initialises this object
	 *
	 * @return object
	 */
	function __construct() {

		// add actions for plugin init on CiviCRM init
		$this->register_hooks();

		// --<
		return $this;

	}



	/**
	 * Set references to other objects
	 *
	 * @param object $parent The parent object
	 * @return void
	 */
	public function set_references( $parent ) {

		// store
		$this->plugin = $parent;

	}



	/**
	 * Register hooks on plugin init
	 *
	 * @return void
	 */
	public function register_hooks() {

		// allow plugin to register php and template directories
		//add_action( 'civicrm_config', array( $this, 'register_directories' ), 10, 1 );

		// intercept CiviEvent create/update/delete actions
		add_action( 'civicrm_post', array( $this, 'event_created' ), 10, 4 );
		add_action( 'civicrm_post', array( $this, 'event_updated' ), 10, 4 );
		add_action( 'civicrm_post', array( $this, 'event_deleted' ), 10, 4 );

		// intercept event type enable/disable
		//add_action( 'civicrm_enableDisable', array( $this, 'event_type_toggle' ), 10, 3 );

		// intercept event type form edits
		add_action( 'civicrm_postProcess', array( $this, 'event_type_process_form' ), 10, 2 );

	}



	/**
	 * Create an EO event when a CiviEvent is created
	 *
	 * @param string $op the type of database operation
	 * @param string $objectName the type of object
	 * @param integer $objectId the ID of the object
	 * @param object $objectRef the object
	 * @return void
	 */
	public function event_created( $op, $objectName, $objectId, $objectRef ) {

		// target our operation
		if ( $op != 'create' ) return;

		// target our object type
		if ( $objectName != 'Event' ) return;

		// kick out if not event object
		if ( ! ( $objectRef instanceof CRM_Event_DAO_Event ) ) return;

		/*
		error_log( print_r( array(
			'method' => __METHOD__,
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		), true ) );
		*/

		// update a single EO event - or create if it doesn't exist
		$event_id = $this->plugin->eo->update_event( (array) $objectRef );

		// kick out if not event object
		if ( is_wp_error( $event_id ) ) {

			// log error
			error_log( print_r( array(
				'method' => __METHOD__,
				'error' => $event_id->get_error_message(),
			), true ) );

			// kick out
			return;

		}

		// get occurrences
		$occurrences = eo_get_the_occurrences_of( $event_id );

		// in this context, a CiviEvent can only have an EO event with a
		// single occurrence associated with it, so use first item
		$keys = array_keys( $occurrences );
		$occurrence_id = array_pop( $keys );

		// store correspondences
		$this->plugin->db->store_event_correspondences( $event_id, array( $occurrence_id => $objectRef->id ) );

	}



	/**
	 * Update an EO event when a CiviEvent is updated
	 *
	 * @param string $op the type of database operation
	 * @param string $objectName the type of object
	 * @param integer $objectId the ID of the object
	 * @param object $objectRef the object
	 * @return void
	 */
	public function event_updated( $op, $objectName, $objectId, $objectRef ) {

		// target our operation
		if ( $op != 'edit' ) return;

		// target our object type
		if ( $objectName != 'Event' ) return;

		// kick out if not event object
		if ( ! ( $objectRef instanceof CRM_Event_DAO_Event ) ) return;

		/*
		error_log( print_r( array(
			'method' => __METHOD__,
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		), true ) );
		*/

		// check for event_type_id, which is a mandatory field
		if ( ! empty( $objectRef->id ) AND empty( $objectRef->event_type_id ) ) {

			// this probably means that a Location has been added to the event
			if ( ! empty( $objectRef->loc_block_id ) ) {

				/*
				error_log( print_r( array(
					'method' => __METHOD__,
					'updated_event' => $updated_event,
				), true ) );
				*/

				// get full event data
				$updated_event = $this->get_event_by_id( $objectRef->id );

				// update the EO event
				$event_id = $this->plugin->eo->update_event( $updated_event );

			} else {

				// what's going on ehre?
				return;

				trigger_error( print_r( array(
					'method' => __METHOD__,
					'op' => $op,
					'objectName' => $objectName,
					'objectId' => $objectId,
					'objectRef' => $objectRef,
				), true ), E_USER_ERROR ); die();

			}

		} else {

			// update a single EO event - or create if it doesn't exist
			$event_id = $this->plugin->eo->update_event( (array) $objectRef );

		}

		// kick out if not event object
		if ( is_wp_error( $event_id ) ) {

			// log error
			error_log( print_r( array(
				'method' => __METHOD__,
				'error' => $event_id->get_error_message(),
			), true ) );

			// kick out
			return;

		}

		// get occurrences
		$occurrences = eo_get_the_occurrences_of( $event_id );

		// in this context, a CiviEvent can only have an EO event with a
		// single occurrence associated with it, so use first item
		$keys = array_keys( $occurrences );
		$occurrence_id = array_pop( $keys );

		// store correspondences
		$this->plugin->db->store_event_correspondences( $event_id, array( $occurrence_id => $objectRef->id ) );

	}



	/**
	 * Delete an EO event when a CiviEvent is updated
	 *
	 * @param string $op the type of database operation
	 * @param string $objectName the type of object
	 * @param integer $objectId the ID of the object
	 * @param object $objectRef the object
	 * @return void
	 */
	public function event_deleted( $op, $objectName, $objectId, $objectRef ) {

		// target our operation
		if ( $op != 'delete' ) return;

		// target our object type
		if ( $objectName != 'Event' ) return;

		// kick out if not event object
		if ( ! ( $objectRef instanceof CRM_Event_DAO_Event ) ) return;

		/*
		error_log( print_r( array(
			'method' => __METHOD__,
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		), true ) );
		*/

	}



	/**
	 * Test if CiviCRM plugin is active
	 *
	 * @return bool
	 */
	public function is_active() {

		// bail if no CiviCRM init function
		if ( ! function_exists( 'civi_wp' ) ) return false;

		// try and init CiviCRM
		return civi_wp()->initialize();

	}



	/**
	 * Register directories that CiviCRM searches for php and template files
	 *
	 * @param object $config The CiviCRM config object
	 * @return void
	 */
	public function register_directories( &$config ) {

		// init CiviCRM or die
		if ( ! $this->is_active() ) return;

		// define our custom path
		$custom_path = CIVICRM_WP_EVENT_ORGANISER_PATH . 'civicrm_custom_templates';

		// get template instance
		$template = CRM_Core_Smarty::singleton();

		// add our custom template directory
		$template->addTemplateDir( $custom_path );

		// register template directories
		$template_include_path = $custom_path . PATH_SEPARATOR . get_include_path();
		set_include_path( $template_include_path );

	}



	//##########################################################################



	/**
	 * Prepare a CiviEvent with data from an EO Event
	 *
	 * @param object $post The WordPress post object
	 * @return array $civi_event The basic CiviEvent data
	 */
	public function prepare_civi_event( $post ) {

		// init CiviEvent array
		$civi_event = array(
			'version' => 3,
		);

		// add items that are common to all CiviEvents
		$civi_event['title'] = $post->post_title;
		$civi_event['description'] = $post->post_content;
		$civi_event['summary'] = strip_tags( $post->post_excerpt );
		$civi_event['created_date'] = $post->post_date;
		$civi_event['is_public'] = 1;
		$civi_event['is_active'] = 1;
		$civi_event['participant_listing_id'] = NULL;

		// get venue for this event
		$venue_id = eo_get_venue( $post->ID );

		// get CiviEvent location
		$location_id = $this->plugin->eo_venue->get_civi_location( $venue_id );

		// did we get one?
		if ( is_numeric( $location_id ) ) {

			// add to our params
			$civi_event['loc_block_id'] = $location_id;

			// set CiviCRM to add map
			$civi_event['is_map'] = 1;

		}

		// online registration off by default
		$civi_event['is_online_registration'] = 0;

		// get CiviEvent online registration value
		$is_reg = $this->plugin->eo->get_event_registration( $post->ID );

		// did we get one?
		if ( is_numeric( $is_reg ) AND $is_reg != 0 ) {

			// add to our params
			$civi_event['is_online_registration'] = 1;

		}

		// participant_role default
		$civi_event['default_role_id'] = 0;

		// get existing role ID
		$existing_id = $this->get_participant_role( $post );

		// did we get one?
		if ( $existing_id !== false AND is_numeric( $existing_id ) AND $existing_id != 0 ) {

			// add to our params
			$civi_event['default_role_id'] = $existing_id;

		}

		// get event type pseudo-ID (or value), because it is required in CiviCRM
		$type_value = $this->get_default_event_type_value( $post );

		// well?
		if ( $type_value === false ) {

			// error
			wp_die( __( 'You must have some CiviCRM event types defined', 'civicrm-event-organiser' ) );

		}

		// assign event type value
		$civi_event['event_type_id'] = $type_value;

		// --<
		return $civi_event;

	}



	/**
	 * Create CiviEvents for an EO event
	 *
	 * @param object $post The WP post object
	 * @param array $dates Array of properly formatted dates
	 * @param array $civi_event_ids Array of new CiviEvent IDs
	 * @return array $correspondences Array of correspondences, keyed by occurrence_id
	 */
	public function create_civi_events( $post, $dates ) {

		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;

		// just for safety, check we get some (though we must)
		if ( count( $dates ) === 0 ) return false;

		// init links
		$links = array();

		// init correspondences
		$correspondences = array();

		// prepare CiviEvent
		$civi_event = $this->prepare_civi_event( $post );

		// now loop through dates and create CiviEvents per date
		foreach ( $dates AS $date ) {

			// overwrite dates
			$civi_event['start_date'] = $date['start'];
			$civi_event['end_date'] = $date['end'];

			// use API to create event
			$result = civicrm_api( 'event', 'create', $civi_event );

			// did we do okay?
			if ( $result['is_error'] == '1' ) {

				// not much else we can do here if we get an error...
				wp_die( $result['error_message'] );

			}

			// add the new CiviEvent ID to array, keyed by occurrence_id
			$correspondences[$date['occurrence_id']] = $result['id'];

			/*
			error_log( print_r( array(
				'method' => __METHOD__,
				'post' => $post,
				'dates' => $dates,
				'civi_event' => $civi_event,
			), true ) );
			*/

		} // end dates loop

		// store these in post meta
		$this->plugin->db->store_event_correspondences( $post->ID, $correspondences );

		// --<
		return $correspondences;

	}



	/**
	 * Update CiviEvents for an event
	 *
	 * @param object $post The WP post object
	 * @param array $dates Array of properly formatted dates
	 * @return array $correspondences Array of correspondences, keyed by occurrence_id
	 */
	public function update_civi_events( $post, $dates ) {

		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;

		// just for safety, check we get some (though we must)
		if ( count( $dates ) === 0 ) return false;

		// get existing CiviEvents from post meta
		$correspondences = $this->plugin->db->get_civi_event_ids_by_eo_event_id( $post->ID );

		/*
		error_log( print_r( array(
			'method' => __METHOD__,
			'post' => $post,
			'dates' => $dates,
			'correspondences' => $correspondences,
		), true ) );
		*/

		// if we have none yet...
		if ( count( $correspondences ) === 0 ) {

			// create them
			$correspondences = $this->create_civi_events( $post, $dates );

			// --<
			return $correspondences;

		}

		/*
		------------------------------------------------------------------------

		The logic for updating is as follows:

		Event sequences can only be generated from EO, so any CiviEvents that are
		part of a sequence must have been generated automatically.

		Since CiviEvents will only be generated when the "Create CiviEvents"
		checkbox is ticked (and only those with 'publish_posts' caps can see the
		checkbox) we assume that this is the definitive set of events.

		Any further changes work thusly:

		We have already got the correspondence array, so retrieve the CiviEvents.
		The correspondence array is already sorted by start date, so the CiviEvents
		will be too.

		If the length of the two event arrays is identical, we assume that the
		sequences correspond and update the CiviEvents with the details of the
		EO events.

		Next, we match by date and time. Any CiviEvents that match have their
		info updated since we assume their correspondence remains unaltered.

		Any additions to the EO event are treated as new CiviEvents and are added
		to CiviCRM. Any removals are treated as if the event has been cancelled
		and the CiviEvent is set to 'disabled' rather than deleted. This is to
		preserve any data that may have been collected for the removed event.

		The bottom line is: make sure your sequences are right before hitting
		the Publish button and be wary of making further changes.

		Things get a bit more complicated when a sequence is split, but it's not
		too bad. This functionality will be handled by the EO 'occurrence' hooks
		when I get round to it.

		Also, note the inline comment discussing what to do with CiviEvents that
		have been "orphaned" from the sequence. The current need is to retain the
		CiviEvent, since there may be associated data.

		------------------------------------------------------------------------
		*/

		// start with new correspondence array
		$new_correspondences = array();

		// sort existing correspondences by key, which will always be chronological
		ksort( $correspondences );

		// prepare CiviEvent
		$civi_event = $this->prepare_civi_event( $post );

		/*
		// trace
		error_log( print_r( array(
			'method' => __METHOD__,
			'post' => $post,
			'dates' => $dates,
			'correspondences' => $correspondences,
			//'civi_event' => $civi_event,
			//'civi_events' => $civi_events,
		), true ) );
		*/

		/*
		------------------------------------------------------------------------
		When arrays are equal in length
		------------------------------------------------------------------------
		*/

		// do the arrays have the same length?
		if ( count( $dates ) === count( $correspondences ) ) {

			// let's assume that the intention is simply to update the CiviEvents
			// and that each date corresponds to the sequential CiviEvent

			// loop through dates
			foreach ( $dates AS $date ) {

				// set ID, triggering update
				$civi_event['id'] = array_shift( $correspondences );

				// overwrite dates
				$civi_event['start_date'] = $date['start'];
				$civi_event['end_date'] = $date['end'];

				// use API to create event
				$result = civicrm_api( 'event', 'create', $civi_event );

				/*
				// trace
				error_log( print_r( array(
					'method' => __METHOD__,
					'civi_event' => $civi_event,
					'result' => $result,
				), true ) );
				*/

				// did we do okay?
				if ( $result['is_error'] == '1' ) {

					// not much else we can do here if we get an error...
					wp_die( $result['error_message'] );

				}

				// add the CiviEvent ID to array, keyed by occurrence_id
				$new_correspondences[$date['occurrence_id']] = $result['id'];

			}

			// overwrite those stored in post meta
			$this->plugin->db->store_event_correspondences( $post->ID, $new_correspondences );

			// --<
			return $new_correspondences;

		}

		/*
		------------------------------------------------------------------------
		When arrays are NOT equal in length, we MUST have correspondences
		------------------------------------------------------------------------
		*/

		// init Civi events array
		$civi_events = array();

		//  get CiviEvents by ID
		foreach ( $correspondences AS $occurrence_id => $civi_event_id ) {

			// add CiviEvent to array
			$civi_events[] = $this->get_event_by_id( $civi_event_id );

		}

		// init orphaned CiviEvent data
		$orphaned_civi_events = array();

		// get orphaned CiviEvents for this EO event
		$orphaned = $this->plugin->db->get_orphaned_events_by_eo_event_id( $post->ID );

		// did we get any?
		if ( count( $orphaned ) > 0 ) {

			//  get CiviEvents by ID
			foreach ( $orphaned AS $civi_event_id ) {

				// add CiviEvent to array
				$orphaned_civi_events[] = $this->get_event_by_id( $civi_event_id );

			}

		}

		/*
		// trace
		error_log( print_r( array(
			'method' => __METHOD__,
			'dates' => $dates,
			'correspondences' => $correspondences,
			'orphaned' => $orphaned,
			'orphaned_civi_events' => $orphaned_civi_events,
		), true ) );
		*/

		// get matches between EO events and CiviEvents
		$matches = $this->get_event_matches( $dates, $civi_events, $orphaned_civi_events );

		// amend the orphans array, removing on what has been "unorphaned"
		$orphans = array_diff( $orphaned, $matches['unorphaned'] );

		/*
		// trace
		error_log( print_r( array(
			'method' => __METHOD__,
			'dates' => $dates,
			'correspondences' => $correspondences,
			'orphaned' => $orphaned,
			//'orphaned_civi_events' => $orphaned_civi_events,
			'matches' => $matches,
			'orphans' => $orphans,
		), true ) );
		*/

		// extract matched array
		$matched = $matches['matched'];

		// do we have any matched?
		if ( count( $matched ) > 0 ) {

			// loop through matched dates and update CiviEvents
			foreach ( $matched AS $occurrence_id => $civi_id ) {

				// assign ID so we perform an update
				$civi_event['id'] = $civi_id;

				// use API to update event
				$result = civicrm_api( 'event', 'create', $civi_event );

				// did we do okay?
				if ( $result['is_error'] == '1' ) {

					// not much else we can do here if we get an error...
					wp_die( $result['error_message'] );

				}

				// add to new correspondence array
				$new_correspondences[$occurrence_id] = $civi_id;

			}

		} // end check for empty array

		// extract unmatched EO events array
		$unmatched_eo = $matches['unmatched_eo'];

		// do we have any unmatched EO occurrences?
		if ( count( $unmatched_eo ) > 0 ) {

			// now loop through unmatched EO dates and create CiviEvents
			foreach ( $unmatched_eo AS $eo_date ) {

				// make sure there's no ID
				unset( $civi_event['id'] );

				// overwrite dates
				$civi_event['start_date'] = $eo_date['start'];
				$civi_event['end_date'] = $eo_date['end'];

				// use API to create event
				$result = civicrm_api( 'event', 'create', $civi_event );

				// did we do okay?
				if ( $result['is_error'] == '1' ) {

					// not much else we can do here if we get an error...
					wp_die( $result['error_message'] );

				}

				// add the CiviEvent ID to array, keyed by occurrence_id
				$new_correspondences[$eo_date['occurrence_id']] = $result['id'];

			}

		} // end check for empty array

		// extract unmatched CiviEvents array
		$unmatched_civi = $matches['unmatched_civi'];

		// do we have any unmatched CiviEvents?
		if ( count( $unmatched_civi ) > 0 ) {

			// assume we're not deleting extra CiviEvents
			$unmatched_delete = false;

			// get "delete unused" checkbox value
			if (
				isset( $_POST['civi_eo_event_delete_unused'] ) AND
				absint( $_POST['civi_eo_event_delete_unused'] ) === 1
			) {

				// override - we ARE deleting
				$unmatched_delete = true;

			}

			// loop through unmatched CiviEvents
			foreach ( $unmatched_civi AS $civi_id ) {

				// if deleting
				if ( $unmatched_delete ) {

					// delete CiviEvent
					$result = $this->delete_civi_events( array( $civi_id ) );

					// delete this ID from the orphans array?
					//$orphans = array_diff( $orphans, array( $civi_id ) );

				} else {

					// set CiviEvent to disabled
					$result = $this->disable_civi_event( $civi_id );

					// add to orphans array
					$orphans[] = $civi_id;

				}

			}

		} // end check for empty array

		// store new correspondences and orphans
		$this->plugin->db->store_event_correspondences( $post->ID, $new_correspondences, $orphans );

	}



	/**
	 * Match EO Events and CiviEvents
	 *
	 * @param array $dates An array of EO event occurrence data
	 * @param array $civi_events An array of CiviEvent data
	 * @param array $orphaned_civi_events An array of orphaned CiviEvent data
	 * @return array $event_data A nested array of matched and unmatched events
	 */
	public function get_event_matches( $dates, $civi_events, $orphaned_civi_events ) {

		// init return array
		$event_data = array(
			'matched' => array(),
			'unmatched_eo' => array(),
			'unmatched_civi' => array(),
			'unorphaned' => array(),
		);

		// init matched
		$matched = array();

		// match EO dates to CiviEvents
		foreach ( $dates AS $key => $date ) {

			// run through CiviEvents
			foreach( $civi_events AS $civi_event ) {

				// does the start_date match?
				if ( $date['start'] == $civi_event['start_date'] ) {

					// add to matched array
					$matched[$date['occurrence_id']] = $civi_event['id'];

					// found - break this loop
					break;

				}

			}

		}

		// init unorphaned
		$unorphaned = array();

		// check orphaned array
		if ( count( $orphaned_civi_events ) > 0 ) {

			// match EO dates to orphaned CiviEvents
			foreach ( $dates AS $key => $date ) {

				// run through orphaned CiviEvents
				foreach( $orphaned_civi_events AS $orphaned_civi_event ) {

					// does the start_date match?
					if ( $date['start'] == $orphaned_civi_event['start_date'] ) {

						// add to matched array
						$matched[$date['occurrence_id']] = $orphaned_civi_event['id'];

						// add to "unorphaned" array
						$unorphaned[] = $orphaned_civi_event['id'];

						// found - break this loop
						break;

					}

				}

			}

		}

		// init EO unmatched
		$unmatched_eo = array();

		// find unmatched EO dates
		foreach ( $dates AS $key => $date ) {

			// if the matched array has no entry
			if ( ! isset( $matched[$date['occurrence_id']] ) ) {

				// add to unmatched
				$unmatched_eo[] = $date;

			}

		}

		// init Civi unmatched
		$unmatched_civi = array();

		// find unmatched EO dates
		foreach( $civi_events AS $civi_event ) {

			// does the matched array have an entry?
			if ( ! in_array( $civi_event['id'], $matched ) ) {

				// add to unmatched
				$unmatched_civi[] = $civi_event['id'];

			}

		}

		// sort matched by key
		ksort( $matched );

		// construct return array
		$event_data['matched'] = $matched;
		$event_data['unmatched_eo'] = $unmatched_eo;
		$event_data['unmatched_civi'] = $unmatched_civi;
		$event_data['unorphaned'] = $unorphaned;

		// --<
		return $event_data;

	}



	/**
	 * Get all CiviEvents
	 *
	 * @return array $events The CiviEvents data
	 */
	public function get_all_civi_events() {

		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;

		// construct events array
		$params = array(
			'version' => 3,
			'is_template' => 0,
			// define stupidly high limit, because API defaults to 25
			'options' => array(
				'limit' => '10000',
			),
		);

		// call API
		$events = civicrm_api( 'event', 'get', $params );

		// --<
		return $events;

	}



	/**
	 * Delete all CiviEvents WARNING only for dev purposes really!
	 *
	 * @param array $civi_event_ids An array of CiviEvent IDs
	 * @return array $results An array of CiviCRM results
	 */
	public function delete_civi_events( $civi_event_ids ) {

		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;

		// just for safety, check we get some
		if ( count( $civi_event_ids ) == 0 ) return false;

		// init return
		$results = array();

		// one by one, it seems
		foreach( $civi_event_ids AS $civi_event_id ) {

			// construct "query"
			$params = array(
				'version' => 3,
				'id' => $civi_event_id,
			);

			// okay, let's do it
			$results[] = civicrm_api( 'event', 'delete', $params );

		}

		// --<
		return $results;

	}



	/**
	 * Disable a CiviEvent
	 *
	 * @param int $civi_event_id The numeric ID of the CiviEvent
	 * @return array $result A CiviCRM result array
	 */
	public function disable_civi_event( $civi_event_id ) {

		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;

		// init event array
		$civi_event = array(
			'version' => 3,
		);

		// assign ID so we perform an update
		$civi_event['id'] = $civi_event_id;

		// set "disabled" flag - see below
		$civi_event['is_active'] = 0;

		// use API to update event
		$result = civicrm_api( 'event', 'create', $civi_event );

		// did we do okay?
		if ( $result['is_error'] == '1' ) {

			// not much else we can do here if we get an error...
			wp_die( $result['error_message'] );

		}

		// --<
		return $result;

	}



	/**
	 * Get a CiviEvent by ID
	 *
	 * @param int $civi_event_id The numeric ID of the CiviEvent
	 * @param array $event The CiviEvent location data
	 */
	public function get_event_by_id( $civi_event_id ) {

		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;

		// construct locations array
		$params = array(
			'version' => 3,
			'id' => $civi_event_id,
		);

		// call API
		$event = civicrm_api( 'event', 'getsingle', $params );

		// --<
		return $event;

	}



	//##########################################################################



	/**
	 * Validate all CiviEvent data for an Event Organiser event
	 *
	 * @param int $post_id The numeric ID of the WP post
	 * @param object $post The WP post object
	 * @return mixed True if success, otherwise WP error object
	 */
	public function validate_civi_options( $post_id, $post ) {

		// disable
		return true;

		// check default event type
		$result = $this->_validate_event_type();
		if ( is_wp_error( $result ) ) return $result;

		// check participant_role
		$result = $this->_validate_participant_role();
		if ( is_wp_error( $result ) ) return $result;

		// check is_online_registration
		$result = $this->_validate_is_online_registration();
		if ( is_wp_error( $result ) ) return $result;

		// check loc_block_id
		$result = $this->_validate_loc_block_id();
		if ( is_wp_error( $result ) ) return $result;

	}



	/**
	 * Updates a CiviEvent Location given an EO venue
	 *
	 * @param array $venue The EO venue data
	 * @param array $location The CiviEvent location data
	 */
	public function update_location( $venue ) {

		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;

		// get existing location
		$location = $this->get_location( $venue );

		// if this venue already has a CiviEvent location
		if (
			$location
			AND
			$location['is_error'] == '0'
			AND
			isset( $location['id'] )
			AND
			is_numeric( $location['id'] )
		) {

			// is there a record on the EO side?
			if ( ! isset( $venue->venue_civi_id ) ) {

				// use the result and fake the property
				$venue->venue_civi_id = $location['id'];

			}

		} else {

			// make sure the property is not set
			$venue->venue_civi_id = 0;

		}

		/*
		error_log( print_r( array(
			'method' => __METHOD__,
			'venue' => $venue,
			'location' => $location,
		), true ) );
		*/

		// update existing - or create one if it doesn't exist
		$location = $this->create_civi_loc_block( $venue );

		// --<
		return $location;

	}



	/**
	 * Delete a CiviEvent Location given an EO venue.
	 *
	 * @param array $venue The EO venue data
	 * @return array $result Civi API result data
	 */
	public function delete_location( $venue ) {

		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;

		// init return
		$result = false;

		// get existing location
		$location = $this->get_location( $venue );

		// did we do okay?
		if (
			$location
			AND
			$location['is_error'] == '0'
			AND
			isset( $location['id'] )
			AND
			is_numeric( $location['id'] )
		) {

			// delete
			$result = $this->delete_location_by_id( $location['id'] );

		}

		// --<
		return $result;

	}



	/**
	 * Delete a CiviEvent Location given a Location ID. Be aware that only the
	 * CiviCRM loc_block is deleted - not the items that constitute it.
	 * Email, phone and address will still exist but not be associated as a loc_block
	 * The next iteration of this plugin should probably refine the loc_block
	 * sync process to take this into account.
	 *
	 * @param int $location_id The numeric ID of the Civi location
	 * @return array $result Civi API result data
	 */
	public function delete_location_by_id( $location_id ) {

		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;

		// construct delete array
		$params = array(
			'version' => 3,
			'id' => $location_id,
		);

		// delete via API
		$result = civicrm_api( 'loc_block', 'delete', $params );

		// did we do okay?
		if ( $result['is_error'] == '1' ) {

			// not much else we can do here if we get an error...
			trigger_error( print_r( array(
				'method' => 'delete_location_by_id',
				'params' => $params,
				'result' => $result,
			), true ), E_USER_ERROR ); die();

		}

		// --<
		return $result;

	}



	/**
	 * Gets a CiviEvent Location given an EO venue
	 *
	 * @param object $venue The EO venue data
	 * @param array $location The CiviEvent location data
	 */
	public function get_location( $venue ) {

		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;

		// ---------------------------------------------------------------------
		// try by sync ID
		// ---------------------------------------------------------------------

		// use it
		$civi_id = 0;

		// if sync ID is present
		if (
			isset( $venue->venue_civi_id )
			AND
			is_numeric( $venue->venue_civi_id )
			AND
			$venue->venue_civi_id > 0
		) {

			// use it
			$civi_id = $venue->venue_civi_id;

		}

		// construct get-by-id array
		$params = array(
			'version' => 3,
			'id' => $civi_id,
			'return' => 'all',
		);

		// call API
		$location = civicrm_api( 'loc_block', 'get', $params );

		// did we do okay?
		if ( $location['is_error'] == '1' ) {

			// not much else we can do here if we get an error...
			wp_die( 'get_location 1: ' . $location['error_message'] );

		}

		/*
		error_log( print_r( array(
			'method' => __METHOD__,
			'venue' => $venue,
			'params' => $params,
			'location' => $location,
		), true ) );
		*/

		// return the result if we get one
		if ( absint( $location['count'] ) > 0 ) return $location;

		// ---------------------------------------------------------------------
		// now try by location
		// ---------------------------------------------------------------------

		// construct get-by-geolocation array
		$params = array(
			'version' => 3,
			'address' => array(
				'geo_code_1' => $venue->venue_lat,
				'geo_code_2' => $venue->venue_lng,
			),
			'return' => 'all',
		);

		// call API
		$location = civicrm_api( 'loc_block', 'get', $params );

		// did we do okay?
		if ( $location['is_error'] == '1' ) {

			// not much else we can do here if we get an error...
			wp_die( 'get_location 2: ' . $location['error_message'] );

		}

		/*
		error_log( print_r( array(
			'method' => __METHOD__,
			'venue' => $venue,
			'params' => $params,
			'location' => $location,
		), true ) );
		*/

		// --<
		return $location;

	}



	/**
	 * Get all CiviEvent Locations
	 *
	 * @param array $locations Array of CiviEvent location data
	 */
	public function get_all_locations() {

		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;

		// construct locations array
		$params = array(

			// API v3 please
			'version' => 3,

			// return all data
			'return' => 'all',

			// define stupidly high limit, because API defaults to 25
			'options' => array(
				'limit' => '10000',
			),

		);

		// call API
		$locations = civicrm_api( 'loc_block', 'get', $params );

		// --<
		return $locations;

	}



	/**
	 * WARNING: deletes all CiviEvent Locations
	 *
	 * @return void
	 */
	public function delete_all_locations() {

		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;

		// get all locations
		$locations = $this->get_all_locations();

		// start again
		foreach( $locations['values'] AS $location ) {

			// construct delete array
			$params = array(
				'version' => 3,
				'id' => $location['id'],
			);

			// delete via API
			$result = civicrm_api( 'loc_block', 'delete', $params );

		}

	}



	/**
	 * Gets a CiviEvent Location given an CiviEvent Location ID
	 *
	 * @param int $loc_id The CiviEvent Location ID
	 * @return array $location The CiviEvent Location data
	 */
	public function get_location_by_id( $loc_id ) {

		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;

		// construct get-by-id array
		$params = array(
			'version' => 3,
			'id' => $loc_id,
			'return' => 'all',
		);

		// call API ('get' returns an array keyed by the item)
		$result = civicrm_api( 'loc_block', 'get', $params );

		// did we do okay?
		if ( $result['is_error'] == '1' || $result['count'] != 1 ) {

			error_log( print_r( array(
				'method' => __METHOD__,
				'params' => $params,
				'result' => $result,
			), true ) );

		}

		// get location from nested array
		$location = array_pop( $result['values'] );

		/*
		error_log( print_r( array(
			'method' => __METHOD__,
			'params' => $params,
			'result' => $result,
			'location' => $location,
		), true ) );
		*/

		// --<
		return $location;

	}



	/**
	 * Creates (or updates) a CiviEvent Location given an EO venue
	 *
	 * The only disadvantage to this method is that, for example, if we update
	 * the email and that email already exists in the DB, it will not be found
	 * and associated - but rather the existing email will be updated. Same goes
	 * for phone. This is not a deal-breaker, but not very DRY either.
	 *
	 * @param object $venue The EO venue object
	 * @return array $location The CiviCRM location data
	 */
	public function create_civi_loc_block( $venue ) {

		// init CiviCRM or die
		if ( ! $this->is_active() ) return array();

		// define create array
		$params = array(
			'version' => 3,
		);

		// first, see if the loc_block email, phone and address already exist

		// if we have an email
		if ( isset( $venue->venue_civi_email ) AND ! empty( $venue->venue_civi_email ) ) {

			// check email
			$email = $this->_query_email( $venue->venue_civi_email );

			// add to params
			$params['email'] = $email;

		}

		// if we have a phone number
		if ( isset( $venue->venue_civi_phone ) AND ! empty( $venue->venue_civi_phone ) ) {

			// check phone
			$phone = $this->_query_phone( $venue->venue_civi_phone );

			// add to params
			$params['phone'] = $phone;

		}

		// check address
		$address = $this->_query_address( $venue );

		// add to params
		$params['address'] = $address;

		// if our venue has a location, add it
		if (
			isset( $venue->venue_civi_id )
			AND
			is_numeric( $venue->venue_civi_id )
			AND
			$venue->venue_civi_id > 0
		) {

			// target our known location - this will trigger an update
			$params['id'] = $venue->venue_civi_id;

		}

		// call API
		$location = civicrm_api( 'loc_block', 'create', $params );

		/*
		error_log( print_r( array(
			'method' => __METHOD__,
			'venue' => $venue,
			'create-update params' => $params,
			'location' => $location,
		), true ) );
		*/

		// did we do okay?
		if ( $location['is_error'] == '1' ) {

			// not much else we can do here if we get an error...
			wp_die( 'create_civi_loc_block 1: ' . $location['error_message'] );

		}

		// we now need to create a dummy CiviEvent, or this venue will not show
		// up in CiviCRM...
		//$this->create_dummy_event( $location );

		// --<
		return $location;

	}



	//##########################################################################



	/**
	 * Get the existing participant role for a post, but fall back to the default
	 * as set on the admin screen. fall back to false otherwise
	 *
	 * @param object $post An EO event object
	 * @return mixed $existing_id The numeric ID of the role, false if none exists
	 */
	public function get_participant_role( $post = null ) {

		// init with impossible ID
		$existing_id = false;

		// do we have a default set?
		$default = $this->plugin->db->option_get( 'civi_eo_event_default_role' );

		// did we get one?
		if ( $default !== '' AND is_numeric( $default ) ) {

			// override with default value
			$existing_id = absint( $default );

		}

		// if we have a post
		if ( isset( $post ) AND is_object( $post ) ) {

			// get stored value
			$stored_id = $this->plugin->eo->get_event_role( $post->ID );

			// did we get one?
			if ( $stored_id !== '' AND is_numeric( $stored_id ) AND $stored_id > 0 ) {

				// override with stored value
				$existing_id = absint( $stored_id );

			}

		}

		// --<
		return $existing_id;

	}



	/**
	 * Get all participant roles
	 *
	 * @param object $post An EO event object
	 * @return mixed $participant_roles Array of Civi role data, false if none exist
	 */
	public function get_participant_roles( $post = null ) {

		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;

		// first, get participant_role option_group ID
		$opt_group = array(
			'version' =>'3',
			'name' =>'participant_role'
		);
		$participant_role = civicrm_api( 'OptionGroup', 'getsingle', $opt_group );

		// next, get option_values for that group
		$opt_values = array(
			'version' =>'3',
			'is_active' => 1,
			'option_group_id' => $participant_role['id']
		);
		$participant_roles = civicrm_api( 'OptionValue', 'get', $opt_values );

		// did we get any?
		if ( $participant_roles['is_error'] == '0' AND count( $participant_roles['values'] ) > 0 ) {

			// --<
			return $participant_roles;

		}

		// --<
		return false;

	}



	/**
	 * Builds a form element for Participant Roles
	 *
	 * @param object $post An EO event object
	 * @return str $html Markup to display in the form
	 */
	public function get_participant_roles_select( $post = null ) {

		// init html
		$html = '';

		// init CiviCRM or die
		if ( ! $this->is_active() ) return $html;

		// first, get all participant_roles
		$all_roles = $this->get_participant_roles();

		// init an array
		$opts = array();

		// did we get any?
		if ( $all_roles['is_error'] == '0' AND count( $all_roles['values'] ) > 0 ) {

			// get the values array
			$roles = $all_roles['values'];

			// init options
			$options = array();

			// get existing role ID
			$existing_id = $this->get_participant_role( $post );

			// loop
			foreach( $roles AS $key => $role ) {

				// get role
				$role_id = absint( $role['value'] );

				// init selected
				$selected = '';

				// is this value the same as in the post?
				if ( $existing_id === $role_id ) {

					// override selected
					$selected = ' selected="selected"';

				}

				// construct option
				$options[] = '<option value="' . $role_id . '"' . $selected . '>' . esc_html( $role['label'] ) . '</option>';

			}

			// create html
			$html = implode( "\n", $options );

		}

		/*
		error_log( print_r( array(
			'method' => __METHOD__,
			'all_roles' => $all_roles,
			'opts' => $opts,
			'options' => $options,
			'html' => $html,
		), true ) );
		*/

		// return
		return $html;

	}



	//##########################################################################



	/**
	 * Checks the status of a CiviEvent's Registration option
	 *
	 * @param object $post The WP event object
	 * @return string $default Checkbox checked or not
	 */
	public function get_registration( $post ) {

		// checkbox unticked by default
		$default = '';

		// sanity check
		if ( ! is_object( $post ) ) return $default;

		// get CiviEvents for this EO event
		$civi_events = $this->plugin->db->get_civi_event_ids_by_eo_event_id( $post->ID );

		//print_r( $civi_events ); die();

		// did we get any?
		if ( is_array( $civi_events ) AND count( $civi_events ) > 0 ) {

			// get the first CiviEvent, though any would do as they all have the same value
			$civi_event = $this->get_event_by_id( array_shift( $civi_events ) );

			// did we do okay?
			if ( $civi_event['is_error'] == '0' AND $civi_event['is_online_registration'] == '1' ) {

				// set checkbox to ticked
				$default = ' checked="checked"';

			}

		}

		// --<
		return $default;

	}



	//##########################################################################



	/**
	 * Intercept when a CiviCRM event type is updated
	 *
	 * Unfortunately, this doesn't work because Civi does not fire hook_civicrm_pre
	 * for this entity type. Sad face.
	 *
	 * @param string $op The type of database operation
	 * @param string $objectName The type of object
	 * @param integer $objectId The ID of the object
	 * @param object $objectRef The object
	 * @return void
	 */
	public function event_type_pre( $op, $objectName, $objectId, $objectRef ) {

		/*
		error_log( print_r( array(
			'method' => __METHOD__,
			'op' => $op,
			'objectName' => $objectName,
			'objectId' => $objectId,
			'objectRef' => $objectRef,
		), true ) );
		*/

		// target our operation
		if ( $op != 'edit' ) return;

		// target our object type
		if ( $objectName != 'Email' ) return;

	}



	/**
	 * Intercept when a CiviCRM event type is toggled
	 *
	 * @param object $recordBAO The CiviCRM option object
	 * @param integer $recordID The ID of the object
	 * @param bool $isActive Whether the item is active or not
	 * @return void
	 */
	public function event_type_toggle( $recordBAO, $recordID, $isActive ) {

		/*

		[recordBAO] => CRM_Core_BAO_OptionValue
		[recordID] => 734
		[isActive] => 1

		trigger_error( print_r( array(
			'recordBAO' => $recordBAO,
			'recordID' => $recordID,
			'isActive' => $isActive,
		), true ), E_USER_ERROR ); die();
		*/

		/*
		WordPress does not have an "Inactive" term state...
		*/

	}



	/**
	 * Update an EO 'event-category' term when a CiviCRM event type is updated
	 *
	 * @param string $formName The CiviCRM form name
	 * @param object $form The CiviCRM form object
	 * @return void
	 */
	public function event_type_process_form( $formName, &$form ) {

		/*
		error_log( print_r( array(
			'method' => __METHOD__,
			'formName' => $formName,
			'form' => $form,
		), true ) );
		*/

		/*
		trigger_error( print_r( array(
			'formName' => $formName,
			'form' => $form,
		), true ), E_USER_ERROR ); die();
		*/

		// kick out if not options form
		if ( ! is_a( $form, 'CRM_Admin_Form_Options' ) ) return;

		// kick out if not event type form
		if ( 'event_type' != $form->getVar( '_gName' ) ) return;

		// inspect all values
		$type = $form->getVar( '_values' );

		// inspect submitted values
		$submitted_values = $form->getVar( '_submitValues' );

		/*
		error_log( print_r( array(
			'method' => __METHOD__,
			'formName' => $formName,
			//'form' => $form,
			'type' => $type,
			'submitted_values' => $submitted_values,
		), true ) );
		*/

		// NOTE: we still need to address the 'is_active' option

		// if our type is populated
		if ( isset( $type['id'] ) ) {

			// it's an update

			// define description if present
			$description = isset( $submitted_values['description'] ) ? $submitted_values['description'] : '';

			// copy existing event type
			$new_type = $type;

			// assemble new event type
			$new_type['label'] = $submitted_values['label'];
			$new_type['name'] = $submitted_values['label'];
			$new_type['description'] = $submitted_values['description'];

			/*
			error_log( print_r( array(
				'method' => __METHOD__,
				'new_type' => $new_type,
				'old_type' => $type,
			), true ) );
			*/

			// unhook EO action
			remove_action( 'edit_terms', array( $this->plugin->eo, 'intercept_pre_update_term' ), 20 );
			remove_action( 'edited_term', array( $this->plugin->eo, 'intercept_pre_update_term' ), 20 );

			// update EO term - or create if it doesn't exist
			$result = $this->plugin->eo->update_term( $new_type, $type );

			// rehook EO actions
			add_action( 'edit_terms', array( $this, 'intercept_pre_update_term' ), 20, 1 );
			add_action( 'edited_term', array( $this, 'intercept_update_term' ), 20, 3 );

		} else {

			// it's an insert

			// define description if present
			$description = isset( $submitted_values['description'] ) ? $submitted_values['description'] : '';

			// construct event type
			$new_type = array(
				'label' => $submitted_values['label'],
				'name' => $submitted_values['label'],
				'description' => $description,
			);

			/*
			error_log( print_r( array(
				'method' => __METHOD__,
				'new_type' => $new_type,
			), true ) );
			*/

			// unhook EO action
			remove_action( 'created_term', array( $this->plugin->eo, 'intercept_create_term' ), 20 );

			// create EO term
			$result = $this->plugin->eo->create_term( $new_type );

			// rehook EO actions
			add_action( 'created_term', array( $this, 'intercept_create_term' ), 20, 3 );

		}

	}



	/**
	 * Update a CiviEvent event type
	 *
	 * @param object $new_term The new EO event category term
	 * @param object $old_term The EO event category term as it was before update
	 * @return ID $type_id The CiviCRM Event Type ID (or false on failure)
	 */
	public function update_event_type( $new_term, $old_term = null ) {

		// sanity check
		if ( ! is_object( $new_term ) ) return false;

		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;

		// get option group ID
		$opt_group_id = $this->get_event_types_optgroup_id();

		// error check
		if ( $opt_group_id === false ) return false;

		// define event type
		$params = array(
			'version' => 3,
			'option_group_id' => $opt_group_id,
			'label' => $new_term->name,
			'name' => $new_term->name,
		);

		// do we have a description?
		if ( $new_term->description != '' ) {

			// add it
			$params['description'] = $new_term->description;

		}

		// if we're updating a term
		if ( ! is_null( $old_term ) ) {

			// get existing event type ID
			$type_id = $this->get_event_type_id( $old_term );

		} else {

			// get matching event type ID
			$type_id = $this->get_event_type_id( $new_term );

		}

		// error check
		if ( $type_id !== false ) {

			// trigger update
			$params['id'] = $type_id;

		}

		// create the event type
		$type = civicrm_api( 'option_value', 'create', $params );

		// error check
		if ( $type['is_error'] == '1' ) {

			// --<
			return false;

			/*
			trigger_error( print_r( array(
				'method' => 'update_event_type',
				//'params' => $params,
				'type' => $type,
			), true ), E_USER_ERROR ); die();
			*/

		}

		// --<
		return $type_id;

	}



	/**
	 * Delete a CiviEvent event type
	 *
	 * @param object $term The EO event category term
	 * @return mixed Civi API data array on success, false on failure
	 */
	public function delete_event_type( $term ) {

		// sanity check
		if ( ! is_object( $term ) ) return false;

		// init CiviCRM or die
		if ( ! $this->is_active() ) return false;

		// get ID of event type to delete
		$type_id = $this->get_event_type_id( $term );

		// error check
		if ( $type_id === false ) return false;

		// define event type
		$params = array(
			'version' => 3,
			'id' => $type_id,
		);

		// delete the event type
		$result = civicrm_api( 'option_value', 'delete', $params );

		//print_r( array( 'result' => $result ) ); die();

		// error check
		if ( $result['is_error'] == '1' ) return false;

		// --<
		return $result;

	}



	/**
	 * Get a CiviEvent event type by term
	 *
	 * @param object $term The EO event category term
	 * @return mixed $type_id The numeric ID of the CiviEvent event type (or false on failure)
	 */
	public function get_event_type_id( $term ) {

		// if we fail to init CiviCRM...
		if ( ! $this->is_active() ) return false;

		// get option group ID
		$opt_group_id = $this->get_event_types_optgroup_id();

		// error check
		if ( $opt_group_id === false ) return false;

		// define params to get item
		$types_params = array(
			'version' => 3,
			'option_group_id' => $opt_group_id,
			'label' => $term->name,
			'options' => array(
				'sort' => 'weight ASC',
			),
		);

		// get the item
		$type = civicrm_api( 'option_value', 'getsingle', $types_params );

		// bail if we get an error
		if ( isset( $type['is_error'] ) AND $type['is_error'] == '1' ) return false;

		// sanity check ID and return if valid
		if ( isset( $type['id'] ) AND is_numeric( $type['id'] ) AND $type['id'] > 0 ) return $type['id'];

		// if all the above fails
		return false;

	}



	/**
	 * Get a CiviEvent event type value by type ID
	 *
	 * @param int $type_id The numeric ID of the CiviEvent event type
	 * @return mixed $value The value of the CiviEvent event type (or false on failure)
	 */
	public function get_event_type_value( $type_id ) {

		// if we fail to init CiviCRM...
		if ( ! $this->is_active() ) return false;

		// get option group ID
		$opt_group_id = $this->get_event_types_optgroup_id();

		// error check
		if ( $opt_group_id === false ) return false;

		// define params to get item
		$types_params = array(
			'version' => 3,
			'option_group_id' => $opt_group_id,
			'id' => $type_id,
		);

		// get the item
		$type = civicrm_api( 'option_value', 'getsingle', $types_params );

		/*
		[type] => Array
			(
				[id] => 115
				[option_group_id] => 15
				[label] => Meeting
				[value] => 4
				[name] => Meeting
				[filter] => 0
				[weight] => 4
				[is_optgroup] => 0
				[is_reserved] => 0
				[is_active] => 1
			)
		*/

		/*
		error_log( print_r( array(
			'method' => __METHOD__,
			'types_params' => $types_params,
			'type' => isset( $type ) ? $type : 'NOT SET',
		), true ) );
		*/

		// error check
		if ( isset( $type['is_error'] ) AND $type['is_error'] == '1' ) {

			trigger_error( print_r( array(
				'method' => 'get_event_type_value',
				'params' => $types_params,
				'type' => $type,
			), true ), E_USER_ERROR ); die();

		}

		// sanity check
		if ( isset( $type['value'] ) AND is_numeric( $type['value'] ) AND $type['value'] > 0 ) return $type['value'];

		// if all the above fails
		return false;

	}



	/**
	 * Get all CiviEvent event types.
	 *
	 * @return mixed $types CiviCRM API return array (or false on failure)
	 */
	public function get_event_types() {

		// if we fail to init CiviCRM...
		if ( ! $this->is_active() ) return false;

		// get option group ID
		$opt_group_id = $this->get_event_types_optgroup_id();

		// error check
		if ( $opt_group_id === false ) return false;

		// define params to get items sorted by weight
		$types_params = array(
			'option_group_id' => $opt_group_id,
			'version' => 3,
			'options' => array(
				'sort' => 'weight ASC',
			),
		);

		// get them (descriptions will be present if not null)
		$types = civicrm_api( 'option_value', 'get', $types_params );

		// error check
		if ( $types['is_error'] == '1' ) return false;

		// --<
		return $types;

	}



	/**
	 * Get all CiviEvent event types formatted as a dropdown list. The pseudo-ID
	 * is actually the event type "value" rather than the event type ID.
	 *
	 * @return str $html Markup containing select options
	 */
	public function get_event_types_select() {

		// init return
		$html = '';

		// init CiviCRM or die
		if ( ! $this->is_active() ) return $html;

		// init an array
		$opts = array();

		// get all event types
		$result = $this->get_event_types();

		// did we get any?
		if ( $result['is_error'] == '0' AND count( $result['values'] ) > 0 ) {

			// get the values array
			$types = $result['values'];

			// init options
			$options = array();

			// get existing type value
			$existing_value = $this->get_default_event_type_value();

			// loop
			foreach( $types AS $key => $type ) {

				// get type value
				$type_value = absint( $type['value'] );

				// init selected
				$selected = '';

				// is this value the same as in the post?
				if ( $existing_value === $type_value ) {

					// override selected
					$selected = ' selected="selected"';

				}

				// construct option
				$options[] = '<option value="' . $type_value . '"' . $selected . '>' . esc_html( $type['label'] ) . '</option>';

			}

			// create html
			$html = implode( "\n", $options );

		}

		/*
		error_log( print_r( array(
			'method' => __METHOD__,
			'result' => $result,
			'opts' => $opts,
			'options' => $options,
			'html' => $html,
		), true ) );
		*/

		// return
		return $html;

	}



	/**
	 * Get the default event type value for a post, but fall back to the default as set
	 * on the admin screen, fall back to false otherwise
	 *
	 * @param object $post The WP event object
	 * @return mixed $existing_id The numeric ID of the CiviEvent event type (or false if none exists)
	 */
	public function get_default_event_type_value( $post = null ) {

		// init with impossible ID
		$existing_value = false;

		// do we have a default set?
		$default = $this->plugin->db->option_get( 'civi_eo_event_default_type' );

		// did we get one?
		if ( $default !== '' AND is_numeric( $default ) ) {

			// override with default value
			$existing_value = absint( $default );

		}

		// if we have a post
		if ( isset( $post ) AND is_object( $post ) ) {

			// get the terms for this post - there should only be one
			$cat = get_the_terms( $post->ID, 'event-category' );

			// error check
			if ( is_wp_error( $cat ) ) return false;

			// did we get any?
			if ( is_array( $cat ) AND count( $cat ) > 0 ) {

				// get first term object (keyed by term ID)
				$term = array_shift( $cat );

				// get type ID for this term
				$existing_id = $this->get_event_type_id( $term );

				// convert to value
				$existing_value = $this->get_event_type_value( $existing_id );

			}

		}

		// --<
		return $existing_value;

	}



	/**
	 * Get a CiviEvent event type by ID
	 *
	 * @param int $type_id The numeric ID of a CiviEvent event type
	 * @return array $type CiviEvent event type data
	 */
	public function get_event_type_by_id( $type_id ) {

		// if we fail to init CiviCRM...
		if ( ! $this->is_active() ) return false;

		// get option group ID
		$opt_group_id = $this->get_event_types_optgroup_id();

		// error check
		if ( $opt_group_id === false ) return false;

		// define params to get item
		$type_params = array(
			'option_group_id' => $opt_group_id,
			'version' => 3,
			'id' => $type_id,
		);

		// get them (descriptions will be present if not null)
		$type = civicrm_api( 'option_value', 'getsingle', $type_params );

		// --<
		return $type;

	}



	/**
	 * Get a CiviEvent event type by "value" pseudo-ID
	 *
	 * @param int $type_value The numeric value of a CiviEvent event type
	 * @return array $type CiviEvent event type data
	 */
	public function get_event_type_by_value( $type_value ) {

		// if we fail to init CiviCRM...
		if ( ! $this->is_active() ) return false;

		// get option group ID
		$opt_group_id = $this->get_event_types_optgroup_id();

		// error check
		if ( $opt_group_id === false ) return false;

		// define params to get item
		$type_params = array(
			'option_group_id' => $opt_group_id,
			'version' => 3,
			'value' => $type_value,
		);

		// get them (descriptions will be present if not null)
		$type = civicrm_api( 'option_value', 'getsingle', $type_params );

		// --<
		return $type;

	}



	/**
	 * Get the CiviEvent event_types option group ID. Multiple calls
	 * to the db are avoided by setting the static variable.
	 *
	 * @return array $types CiviCRM API return array (or false on failure)
	 */
	public function get_event_types_optgroup_id() {

		// init
		static $optgroup_id;

		// do we have it?
		if ( ! isset( $optgroup_id ) ) {

			// if we fail to init CiviCRM...
			if ( ! $this->is_active() ) {

				// set flag to false for future reference
				$optgroup_id = false;

				// --<
				return $optgroup_id;

			}

			// define params to get event type option group
			$opt_group_params = array(
				'name' => 'event_type',
				'version' => 3,
			);

			// get it
			$opt_group = civicrm_api( 'option_group', 'getsingle', $opt_group_params );

			// error check
			if ( isset( $opt_group['id'] ) AND is_numeric( $opt_group['id'] ) AND $opt_group['id'] > 0 ) {

				// set flag to false for future reference
				$optgroup_id = $opt_group['id'];

				// --<
				return $optgroup_id;

			}

		}

		// --<
		return $optgroup_id;

	}



	//##########################################################################



	/**
	 * Query email via API
	 *
	 * @param string $email
	 * @return mixed $email_data Integer if found, array if not found
	 */
	private function _query_email( $email ) {

		// check email
		$email_params = array(
			'version' => 3,
			'contact_id' => null,
			'is_primary' => 0,
			'location_type_id' => 1,
			'email' => $email,
		);

		// query API
		$existing_email_data = civicrm_api( 'email', 'get', $email_params );

		// did we get one?
		if ( $existing_email_data['is_error'] == 0 AND $existing_email_data['count'] > 0 ) {

			// get first one
			$existing_email = array_shift( $existing_email_data['values'] );

			// get its ID
			$email_data = $existing_email['id'];

		} else {

			// define new email
			$email_data = array(
				'location_type_id' => 1,
				'email' => $email,
			);

		}

		/*
		error_log( print_r( array(
			'method' => __METHOD__,
			'email_params' => $email_params,
			'existing_email_data' => $existing_email_data,
			'email_data' => $email_data,
		), true ) );
		*/

		// --<
		return $email_data;

	}



	/**
	 * Query phone via API
	 *
	 * @param string $phone
	 * @return mixed $phone_data Integer if found, array if not found
	 */
	private function _query_phone( $phone ) {

		// create numeric version of phone number
		$numeric = preg_replace( "/[^0-9]/", '', $phone );

		// check phone by its numeric field
		$phone_params = array(
			'version' => 3,
			'contact_id' => null,
			'is_primary' => 0,
			'location_type_id' => 1,
			'phone_numeric' => $numeric,
		);

		// query API
		$existing_phone_data = civicrm_api( 'phone', 'get', $phone_params );

		// did we get one?
		if ( $existing_phone_data['is_error'] == 0 AND $existing_phone_data['count'] > 0 ) {

			// get first one
			$existing_phone = array_shift( $existing_phone_data['values'] );

			// get its ID
			$phone_data = $existing_phone['id'];

		} else {

			// define new phone
			$phone_data = array(
				'location_type_id' => 1,
				'phone' => $phone,
				'phone_numeric' => $numeric,
			);

		}

		/*
		error_log( print_r( array(
			'method' => __METHOD__,
			'phone_params' => $phone_params,
			'existing_phone_data' => $existing_phone_data,
			'phone_data' => $phone_data,
		), true ) );
		*/

		// --<
		return $phone_data;

	}



	/**
	 * Query address via API
	 *
	 * @param object $venue
	 * @return mixed $address_data Integer if found, array if not found
	 */
	private function _query_address( $venue ) {

		// check address
		$address_params = array(
			'version' => 3,
			'contact_id' => null,
			'is_primary' => 0,
			'location_type_id' => 1,
			'street_address' => $venue->venue_address,
			'city' => $venue->venue_city,
			//'county' => $venue->venue_state, // can't do county in CiviCRM yet
			'postal_code' => $venue->venue_postcode,
			//'country' => $venue->venue_country, // can't do country in CiviCRM yet
			'geo_code_1' => $venue->venue_lat,
			'geo_code_2' => $venue->venue_lng,
		);

		// query API
		$existing_address_data = civicrm_api( 'address', 'get', $address_params );

		// did we get one?
		if ( $existing_address_data['is_error'] == 0 AND $existing_address_data['count'] > 0 ) {

			// get first one
			$existing_address = array_shift( $existing_address_data['values'] );

			// get its ID
			$address_data = $existing_address['id'];

		} else {

			// define new address
			$address_data = array(
				'location_type_id' => 1,
				'street_address' => $venue->venue_address,
				'city' => $venue->venue_city,
				//'county' => $venue->venue_state, // can't do county in CiviCRM yet
				'postal_code' => $venue->venue_postcode,
				//'country' => $venue->venue_country, // can't do country in CiviCRM yet
				'geo_code_1' => $venue->venue_lat,
				'geo_code_2' => $venue->venue_lng,
			);

		}

		/*
		error_log( print_r( array(
			'method' => __METHOD__,
			'address_params' => $address_params,
			'existing_address_data' => $existing_address_data,
			'address_data' => $address_data,
		), true ) );
		*/

		// --<
		return $address_data;

	}



	/**
	 * Debugging
	 *
	 * @param str $msg
	 * @return void
	 */
	private function _debug( $msg ) {

		// add to internal array
		$this->messages[] = $msg;

		// do we want output?
		if ( CIVICRM_WP_EVENT_ORGANISER_DEBUG ) print_r( $msg );

	}



} // class ends



