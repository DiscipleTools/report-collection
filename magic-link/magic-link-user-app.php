<?php
if ( !defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly.


/**
 * Class Disciple_Tools_Survey_Collection_Magic_User_App
 */
class Disciple_Tools_Survey_Collection_Magic_User_App extends DT_Magic_Url_Base {

    public $page_title = 'Report Survey Collection';
    public $page_description = 'Report Survey Collection';
    public $root = 'rsc_magic_app'; // @todo define the root of the url {yoursite}/root/type/key/action
    public $type = 'rsc_user_app'; // @todo define the type
    public $post_type = 'user';
    private $meta_key = '';
    public $show_bulk_send = false;
    public $show_app_tile = true; // show this magic link in the Apps tile on the post record

    private static $_instance = null;
    public $meta = []; // Allows for instance specific data.

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __construct() {

        /**
         * Specify metadata structure, specific to the processing of current
         * magic link type.
         *
         * - meta:              Magic link plugin related data.
         *      - app_type:     Flag indicating type to be processed by magic link plugin.
         *      - post_type     Magic link type post type.
         *      - contacts_only:    Boolean flag indicating how magic link type user assignments are to be handled within magic link plugin.
         *                          If True, lookup field to be provided within plugin for contacts only searching.
         *                          If false, Dropdown option to be provided for user, team or group selection.
         *      - fields:       List of fields to be displayed within magic link frontend form.
         */
        $this->meta = [
            'app_type'       => 'magic_link',
            'post_type'      => $this->post_type,
            'contacts_only'  => false,
            'fields'         => self::build_meta_report_survey_collection_fields(),
            'fields_refresh' => [
                'enabled'    => true,
                'post_type'  => 'reports',
                'ignore_ids' => []
            ]
        ];

        $this->meta_key = $this->root . '_' . $this->type . '_magic_key';
        parent::__construct();

        /**
         * user_app and module section
         */
        add_filter( 'dt_settings_apps_list', [ $this, 'dt_settings_apps_list' ], 10, 1 );
        add_action( 'rest_api_init', [ $this, 'add_endpoints' ] );

        /**
         * tests if other URL
         */
        $url = dt_get_url_path();
        if ( strpos( $url, $this->root . '/' . $this->type ) === false ) {
            return;
        }
        /**
         * tests magic link parts are registered and have valid elements
         */
        if ( !$this->check_parts_match() ){
            return;
        }

        // load if valid url
        add_action( 'dt_blank_body', [ $this, 'body' ] );
        add_filter( 'dt_magic_url_base_allowed_css', [ $this, 'dt_magic_url_base_allowed_css' ], 10, 1 );
        add_filter( 'dt_magic_url_base_allowed_js', [ $this, 'dt_magic_url_base_allowed_js' ], 10, 1 );
        add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ], 100 );

    }

    private function get_supported_field_tiles(): array {
        return [ 'tracking' ];
    }

    private function build_meta_report_survey_collection_fields(): array {
        $report_survey_collection_fields = [];

        // Iterate over corresponding report fields, extracting those flagged for processing.
        $fields = DT_Posts::get_post_field_settings( 'reports', false );
        foreach ( $fields ?? [] as $field_key => $field ) {

            // If magic link enabled and part of target tile.
            if ( isset( $field['tile'] ) && in_array( $field['tile'], self::get_supported_field_tiles() ) ) {
                $report_survey_collection_fields[] = [
                    'id'    => $field_key,
                    'label' => $field['name']
                ];
            }
        }

        return $report_survey_collection_fields;
    }

    public function wp_enqueue_scripts() {
        // Support Geolocation APIs
        if ( DT_Mapbox_API::get_key() ) {
            DT_Mapbox_API::load_mapbox_header_scripts();
            DT_Mapbox_API::load_mapbox_search_widget();
        }

        // Support Typeahead APIs
        $path     = '/dt-core/dependencies/typeahead/dist/';
        $path_js  = $path . 'jquery.typeahead.min.js';
        $path_css = $path . 'jquery.typeahead.min.css';
        wp_enqueue_script( 'jquery-typeahead', get_template_directory_uri() . $path_js, [ 'jquery' ], filemtime( get_template_directory() . $path_js ) );
        wp_enqueue_style( 'jquery-typeahead-css', get_template_directory_uri() . $path_css, [], filemtime( get_template_directory() . $path_css ) );

        wp_enqueue_style( 'toastify-js-css', 'https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.css', [], '1.12.0' );
        wp_enqueue_script( 'toastify-js', 'https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.js', [ 'jquery' ], '1.12.0' );

        wp_enqueue_style( 'material-font-icons-css', 'https://cdn.jsdelivr.net/npm/@mdi/font@6.6.96/css/materialdesignicons.min.css', [], '6.6.96' );
    }

    public function dt_magic_url_base_allowed_js( $allowed_js ) {
        $allowed_js[] = 'mapbox-gl';
        $allowed_js[] = 'mapbox-cookie';
        $allowed_js[] = 'mapbox-search-widget';
        $allowed_js[] = 'jquery-typeahead';
        $allowed_js[] = 'toastify-js';

        return $allowed_js;
    }

    public function dt_magic_url_base_allowed_css( $allowed_css ) {
        $allowed_css[] = 'mapbox-gl-css';
        $allowed_css[] = 'jquery-typeahead-css';
        $allowed_css[] = 'material-font-icons-css';
        $allowed_css[] = 'toastify-js-css';

        return $allowed_css;
    }

    /**
     * Builds magic link type settings payload:
     * - key:               Unique magic link type key; which is usually composed of root, type and _magic_key suffix.
     * - url_base:          URL path information to map with parent magic link type.
     * - label:             Magic link type name.
     * - description:       Magic link type description.
     * - settings_display:  Boolean flag which determines if magic link type is to be listed within frontend user profile settings.
     *
     * @param $apps_list
     *
     * @return mixed
     */
    public function dt_settings_apps_list( $apps_list ) {
        $apps_list[ $this->meta_key ] = [
            'key'              => $this->meta_key,
            'url_base'         => $this->root . '/' . $this->type,
            'label'            => $this->page_title,
            'description'      => $this->page_description,
            'settings_display' => true
        ];

        return $apps_list;
    }

    /**
     * Writes custom styles to header
     *
     * @see DT_Magic_Url_Base()->header_style() for default state
     * @todo remove if not needed
     */
    public function header_style() {
        ?>
        <style>
            body {
                background-color: white;
                padding: 1em;
            }

            .api-content-div-style {
                max-height: 300px;
                overflow-x: hidden;
                overflow-y: scroll;
                text-align: left;
            }

            .api-content-table tbody {
                border: none;
            }

            .api-content-table tr {
                cursor: pointer;
                background: #ffffff;
                padding: 0px;
            }

            .api-content-table tr:hover {
                background-color: #f5f5f5;
            }

            .highlight-selected {
                background-color: #f5f5f5 !important;
            }
        </style>
        <?php
    }

    /**
     * Writes javascript to the header
     *
     * @see DT_Magic_Url_Base()->header_javascript() for default state
     * @todo remove if not needed
     */
    public function header_javascript(){
        ?>
        <script></script>
        <?php
    }

    /**
     * Writes javascript to the footer
     *
     * @see DT_Magic_Url_Base()->footer_javascript() for default state
     * @todo remove if not needed
     */
    public function footer_javascript(){
        ?>
        <script>
            let jsObject = [<?php echo json_encode( [
                'map_key'                 => DT_Mapbox_API::get_key(),
                'root'                    => esc_url_raw( rest_url() ),
                'nonce'                   => wp_create_nonce( 'wp_rest' ),
                'parts'                   => $this->parts,
                'link_obj_id'             => $this->fetch_incoming_link_param( 'id' ),
                'sys_type'                => $this->fetch_incoming_link_param( 'type' ),
                'reports_field_settings'  => DT_Posts::get_post_field_settings( 'reports', false ),
                'translations'            => [
                    'regions_of_focus' => __( 'Regions of Focus', 'disciple_tools' ),
                    'all_locations'    => __( 'All Locations', 'disciple_tools' ),
                    'help'             => [
                        'title'     => __( 'Field Description', 'disciple_tools' ),
                        'close_but' => __( 'Close', 'disciple_tools' )
                    ]
                ],
                'mapbox'                  => [
                    'map_key'        => DT_Mapbox_API::get_key(),
                    'google_map_key' => Disciple_Tools_Google_Geocode_API::get_key(),
                    'translations'   => [
                        'search_location' => __( 'Search Location', 'disciple_tools' ),
                        'delete_location' => __( 'Delete Location', 'disciple_tools' ),
                        'use'             => __( 'Use', 'disciple_tools' ),
                        'open_modal'      => __( 'Open Modal', 'disciple_tools' )
                    ]
                ],
                'submit_success_function' => Disciple_Tools_Bulk_Magic_Link_Sender_API::get_link_submission_success_js_code()
            ] ) ?>][0];

            /**
             * Fetch assigned reports
             */

            window.get_magic = () => {
                jQuery.ajax({
                    type: "GET",
                    data: {
                        action: 'get',
                        parts: jsObject.parts,
                        limit: 5
                    },
                    contentType: "application/json; charset=utf-8",
                    dataType: "json",
                    url: jsObject.root + jsObject.parts.root + '/v1/' + jsObject.parts.type,
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', jsObject.nonce)
                    }
                })
                    .done(function (data) {
                        window.load_magic(data)
                    })
                    .fail(function (e) {
                        console.log(e)
                        jQuery('#error').html(e)
                    })
            };
            window.get_magic();

            /**
             * Display returned list of assigned reports
             */

            window.load_magic = (data) => {
                let content = jQuery('#api-content');
                let spinner = jQuery('.list-loading-spinner');
                let table = jQuery('.api-content-table');
                let total = jQuery('#total');
                let error = jQuery('#error');

                // Clear any previous errors
                error.html('');

                // Remove any previous entries
                table.find('tbody').empty();

                // Set total hits count
                total.html(data['total'] ? data['total'] : '0');

                // Iterate over returned posts
                if (data['posts']) {
                    data['posts'].forEach(v => {
                        let html = `<tr id="report_${window.lodash.escape(v.id)}" class="report-list-item" onclick="get_assigned_report_details('${window.lodash.escape(v.id)}', '${window.lodash.escape(v.name)}');">
                                <td>${window.lodash.escape(v.name)}</td>
                            </tr>`;
                        table.find('tbody').append(html);
                    });
                }
                spinner.removeClass('active');
            };

            /**
             * Package & render field tr html
             */

            window.generate_field_tr_html = (id, type, html, last) => {
                return `<tr>
                            <td style="width: 5%;">
                                <button class="button select-button" onclick="display_report_field_help('${window.lodash.escape(id)}')">
                                    <i class="mdi mdi-help"></i>
                                </button>
                            </td>
                            <td>
                                <input id="form_content_table_field_id" type="hidden" value="${window.lodash.escape(id)}" />
                                <input id="form_content_table_field_type" type="hidden" value="${window.lodash.escape(type)}" />
                                <input id="form_content_table_field_meta" type="hidden" value="" />
                                ${html}
                            </td>
                            <td>
                                ${window.lodash.escape(window.extract_last_value(last, id, type))}
                            </td>
                        </tr>`;
            };
            window.extract_last_value = (last, field_id, field_type) => {
                if (last && last[field_id]) {
                    switch (field_type) {
                        case 'number':
                        case 'textarea':
                        case 'text':
                        case 'key_select': {
                            return last[field_id];
                        }
                        case 'date': {
                            return last[field_id]['formatted'];
                        }
                    }
                }

                return '';
            };
            window.display_report_field_help = (field_id) => {
                let dialog = $('#report_field_help_dialog');
                let field = jsObject.reports_field_settings[field_id];

                if (dialog && field['name'] && field['description']) {

                    // Update dialog div
                    $(dialog).empty().append(field['description']);

                    // Refresh dialog config
                    dialog.dialog({
                        modal: true,
                        autoOpen: false,
                        hide: 'fade',
                        show: 'fade',
                        height: 300,
                        width: 450,
                        resizable: true,
                        title: jsObject.translations['help']['title'] + ' - ' + field['name'],
                        buttons: [
                            {
                                text: jsObject.translations['help']['close_but'],
                                icon: 'ui-icon-close',
                                click: function () {
                                    $(this).dialog('close');
                                }
                            }
                        ],
                        open: function (event, ui) {
                        }
                    });

                    // Display updated dialog
                    dialog.dialog('open');
                }
            };

            /**
             * Handle new report requests
             */

            jQuery('#new_report_but').on('click', function () {
                let spinner = jQuery('.details-loading-spinner');
                let table = jQuery('.form-content-table');
                let error = jQuery('#error');

                // Clear any previous errors
                error.html('');

                // Refresh report list item highlights
                window.refresh_report_list_item_highlights(null);

                table.fadeOut('fast', function () {
                    spinner.addClass('active');

                    // Remove any previous report titles.
                    jQuery('#report_name').html('');

                    // Remove any previous entries.
                    table.find('tbody').empty();

                    // Hide submit button.
                    jQuery('#content_submit_but').fadeIn('fast');

                    jQuery.ajax({
                        type: "GET",
                        data: {
                            action: 'get',
                            parts: jsObject.parts,
                            link_obj_id: jsObject.link_obj_id,
                            ts: moment().unix() // Alter url shape, to force cache refresh!
                        },
                        contentType: "application/json; charset=utf-8",
                        dataType: "json",
                        url: jsObject.root + jsObject.parts.root + '/v1/' + jsObject.parts.type + '/new_post',
                        beforeSend: function (xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', jsObject.nonce);
                            xhr.setRequestHeader('Cache-Control', 'no-store');
                        }

                    }).done(function (data) {
                        if (data['fields']) {

                            // Reset post id, to indicate new report post.
                            jQuery('#post_id').val('');
                            jQuery('#post_state').val('new');

                            // Iterate over, display and activate returned post fields.
                            data['fields'].forEach(field => {
                                table.find('tbody').append(window.generate_field_tr_html(field['id'], field['type'], field['html'], data['last']));
                            });

                            // Activate recently rendered field elements.
                            window.activate_field_controls(null);

                            // Refresh report comments text area.
                            window.refresh_report_comments(0, []);

                            // Display activated fields.
                            table.fadeIn('fast');

                            // Display submit button.
                            jQuery('#content_submit_but').fadeIn('fast');
                        }

                        // Remove details spinner.
                        spinner.removeClass('active');

                    }).fail(function (e) {
                        spinner.removeClass('active');

                        console.log(e);
                        jQuery('#error').html(e);
                    });
                });
            });

            /**
             * Handle fetch request for report details
             */

            window.get_assigned_report_details = (post_id, post_name) => {
                let report_name = jQuery('#report_name');

                // Update report name
                report_name.html(post_name);

                // Refresh report list item highlights
                window.refresh_report_list_item_highlights(post_id);

                // Fetch requested report details
                window.get_report(post_id);
            };
            window.refresh_report_list_item_highlights = (post_id) => {
                $('.report-list-item').removeClass('highlight-selected');
                if (post_id) {
                    $('#report_' + post_id).addClass('highlight-selected');
                }
            };

            /**
             * Fetch requested report details
             */

            window.get_report = (post_id) => {
                let spinner = jQuery('.details-loading-spinner');
                let table = jQuery('.form-content-table');
                let error = jQuery('#error');
                let comment_count = 2;

                // Clear any previous errors
                error.html('');

                table.fadeOut('fast', function () {
                    spinner.addClass('active');

                    // Remove any previous entries.
                    table.find('tbody').empty();

                    // Hide submit button.
                    jQuery('#content_submit_but').fadeIn('fast');

                    // Dispatch request call.
                    jQuery.ajax({
                        type: "GET",
                        data: {
                            action: 'get',
                            parts: jsObject.parts,
                            sys_type: jsObject.sys_type,
                            post_id: post_id,
                            link_obj_id: jsObject.link_obj_id,
                            comment_count: comment_count,
                            ts: moment().unix() // Alter url shape, to force cache refresh!
                        },
                        contentType: "application/json; charset=utf-8",
                        dataType: "json",
                        url: jsObject.root + jsObject.parts.root + '/v1/' + jsObject.parts.type + '/post',
                        beforeSend: function (xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', jsObject.nonce);
                            xhr.setRequestHeader('Cache-Control', 'no-store');
                        }
                    }).done(function (data) {
                        if (data['success'] && data['post'] && data['fields']) {

                            // Capture post id for further downstream processing.
                            jQuery('#post_id').val(data['post']['ID']);
                            jQuery('#post_state').val('update');

                            // Iterate over, display and activate returned post fields.
                            data['fields'].forEach(field => {
                                table.find('tbody').append(window.generate_field_tr_html(field['id'], field['type'], field['html'], data['last']));
                            });

                            // Activate recently rendered field elements.
                            window.activate_field_controls(data['post']);

                            // Refresh report comments text area.
                            window.refresh_report_comments(comment_count, data['comments']['comments']);

                            // Display activated fields.
                            table.fadeIn('fast');

                            // Display submit button.
                            jQuery('#content_submit_but').fadeIn('fast');
                        }

                        // Remove details spinner.
                        spinner.removeClass('active');

                    }).fail(function (e) {
                        spinner.removeClass('active');

                        console.log(e);
                        jQuery('#error').html(e);
                    });
                });
            };

            /**
             * Activate various field controls.
             */

            window.activate_field_controls = (post) => {
                jQuery('.form-content-table > tbody > tr').each(function (idx, tr) {
                    let field_id = jQuery(tr).find('#form_content_table_field_id').val();
                    let field_type = jQuery(tr).find('#form_content_table_field_type').val();
                    let field_meta = jQuery(tr).find('#form_content_table_field_meta');

                    // Activate field accordingly by field type
                    switch (field_type) {
                        case 'communication_channel': {

                            /**
                             * Add
                             */

                            jQuery(tr).find('button.add-button').on('click', evt => {
                                let field = jQuery(evt.currentTarget).data('list-class');
                                let list = jQuery(tr).find(`#edit-${field}`);

                                list.append(`
                                        <div class="input-group">
                                            <input type="text" data-field="${window.lodash.escape(field)}" class="dt-communication-channel input-group-field" dir="auto" />
                                            <div class="input-group-button">
                                                <button class="button alert input-height delete-button-style channel-delete-button delete-button new-${window.lodash.escape(field)}" data-key="new" data-field="${window.lodash.escape(field)}">&times;</button>
                                            </div>
                                        </div>`);
                            });

                            /**
                             * Remove
                             */

                            jQuery(document).on('click', '.channel-delete-button', evt => {
                                let field = jQuery(evt.currentTarget).data('field');
                                let key = jQuery(evt.currentTarget).data('key');

                                // If needed, keep a record of key for future api removal.
                                if (key !== 'new') {
                                    let deleted_keys = (field_meta.val()) ? JSON.parse(field_meta.val()) : [];
                                    deleted_keys.push(key);
                                    field_meta.val(JSON.stringify(deleted_keys));
                                }

                                // Final removal of input group
                                jQuery(evt.currentTarget).parent().parent().remove();
                            });

                            break;
                        }

                        case 'location_meta': {

                            /**
                             * Add
                             */

                            jQuery(tr).find('#new-mapbox-search').on('click', evt => {

                                // Display search field with autosubmit disabled!
                                if (jQuery(tr).find('#mapbox-autocomplete').length === 0) {
                                    jQuery(tr).find('#mapbox-wrapper').prepend(`
                                        <div id="mapbox-autocomplete" class="mapbox-autocomplete input-group" data-autosubmit="false">
                                            <input id="mapbox-search" type="text" name="mapbox_search" placeholder="${window.lodash.escape(jsObject['mapbox']['translations']['search_location'])}" autocomplete="off" dir="auto" />
                                            <div class="input-group-button">
                                                <button id="mapbox-spinner-button" class="button hollow" style="display:none;"><span class="loading-spinner active"></span></button>
                                                <button id="mapbox-clear-autocomplete" class="button alert input-height delete-button-style mapbox-delete-button" type="button" title="${window.lodash.escape(jsObject['mapbox']['translations']['delete_location'])}" >&times;</button>
                                            </div>
                                            <div id="mapbox-autocomplete-list" class="mapbox-autocomplete-items"></div>
                                        </div>`);
                                }

                                // Switch over to standard workflow, with autosubmit disabled!
                                write_input_widget();
                            });

                            // Hide new button and default to single entry
                            jQuery(tr).find('#new-mapbox-search').hide();
                            jQuery(tr).find('#new-mapbox-search').trigger('click');

                            /**
                             * Remove
                             */

                            jQuery(document).on('click', '.mapbox-delete-button', evt => {
                                let id = jQuery(evt.currentTarget).data('id');

                                // If needed, keep a record of key for future api removal.
                                if (id !== undefined) {
                                    let deleted_ids = (field_meta.val()) ? JSON.parse(field_meta.val()) : [];
                                    deleted_ids.push(id);
                                    field_meta.val(JSON.stringify(deleted_ids));

                                    // Final removal of input group
                                    jQuery(evt.currentTarget).parent().parent().remove();

                                } else {

                                    // Remove global selected location
                                    window.selected_location_grid_meta = null;
                                }
                            });

                            break;
                        }

                        case 'location': {

                            /**
                             * Load Typeahead
                             */

                            let typeahead_field_input = '.js-typeahead-' + field_id;
                            if (!window.Typeahead[typeahead_field_input]) {
                                jQuery(tr).find(typeahead_field_input).typeahead({
                                    input: typeahead_field_input,
                                    minLength: 0,
                                    accent: true,
                                    searchOnFocus: true,
                                    maxItem: 20,
                                    dropdownFilter: [{
                                        key: 'group',
                                        value: 'focus',
                                        template: window.lodash.escape(jsObject['translations']['regions_of_focus']),
                                        all: window.lodash.escape(jsObject['translations']['all_locations'])
                                    }],
                                    source: {
                                        focus: {
                                            display: "name",
                                            ajax: {
                                                url: jsObject['root'] + 'dt/v1/mapping_module/search_location_grid_by_name',
                                                data: {
                                                    s: "{{query}}",
                                                    filter: function () {
                                                        return window.lodash.get(window.Typeahead[typeahead_field_input].filters.dropdown, 'value', 'all');
                                                    }
                                                },
                                                beforeSend: function (xhr) {
                                                    xhr.setRequestHeader('X-WP-Nonce', jsObject['nonce']);
                                                },
                                                callback: {
                                                    done: function (data) {
                                                        return data.location_grid;
                                                    }
                                                }
                                            }
                                        }
                                    },
                                    display: "name",
                                    templateValue: "{{name}}",
                                    dynamic: true,
                                    multiselect: {
                                        matchOn: ["ID"],
                                        data: function () {
                                            return [];
                                        }, callback: {
                                            onCancel: function (node, item) {

                                                // Keep a record of deleted options
                                                let deleted_items = (field_meta.val()) ? JSON.parse(field_meta.val()) : [];
                                                deleted_items.push(item);
                                                field_meta.val(JSON.stringify(deleted_items));

                                            }
                                        }
                                    },
                                    callback: {
                                        onClick: function (node, a, item, event) {
                                        },
                                        onReady() {
                                            this.filters.dropdown = {
                                                key: "group",
                                                value: "focus",
                                                template: window.lodash.escape(jsObject['translations']['regions_of_focus'])
                                            };
                                            this.container
                                                .removeClass("filter")
                                                .find("." + this.options.selector.filterButton)
                                                .html(window.lodash.escape(jsObject['translations']['regions_of_focus']));
                                        }
                                    }
                                });
                            }

                            break;
                        }

                        case 'multi_select': {

                            /**
                             * Handle Selections
                             */

                            jQuery(tr).find('.dt_multi_select').on("click", function (evt) {
                                let multi_select = jQuery(evt.currentTarget);
                                if (multi_select.hasClass('empty-select-button')) {
                                    multi_select.removeClass('empty-select-button');
                                    multi_select.addClass('selected-select-button');
                                } else {
                                    multi_select.removeClass('selected-select-button');
                                    multi_select.addClass('empty-select-button');
                                }
                            });

                            break;
                        }

                        case 'date': {

                            /**
                             * Load Date Range Picker
                             */

                            let date_config = {
                                singleDatePicker: true,
                                timePicker: true,
                                locale: {
                                    format: 'MMMM D, YYYY'
                                }
                            };

                            // Adjust start date based on post's date timestamp; if present
                            if (post && post[field_id] && post[field_id]['timestamp']) {
                                let start_ts = post[field_id]['timestamp'];
                                date_config['startDate'] = moment.unix(start_ts);
                                field_meta.val(start_ts);

                            } else {

                                // Default to current timestamp
                                field_meta.val(moment().unix());
                            }

                            // Initialise date range picker and respond to selections
                            jQuery(tr).find('#' + field_id).daterangepicker(date_config, function (start, end, label) {
                                if (start) {
                                    field_meta.val(start.unix());
                                }
                            });

                            /**
                             * Clear Date
                             */

                            jQuery(tr).find('.clear-date-button').on('click', evt => {
                                let input_id = jQuery(evt.currentTarget).data('inputid');

                                if (input_id) {
                                    jQuery(tr).find('#' + input_id).val('');
                                    field_meta.val('');
                                }
                            });

                            break;
                        }

                        case 'tags': {

                            /**
                             * Activate
                             */

                            // Hide new button and default to single entry
                            jQuery(tr).find('.create-new-tag').hide();

                            let typeahead_tags_field_input = '.js-typeahead-' + field_id;
                            if (!window.Typeahead[typeahead_tags_field_input]) {
                                jQuery(tr).find(typeahead_tags_field_input).typeahead({
                                    input: typeahead_tags_field_input,
                                    minLength: 0,
                                    maxItem: 20,
                                    searchOnFocus: true,
                                    source: {
                                        tags: {
                                            display: ["name"],
                                            ajax: {
                                                url: jsObject['root'] + `dt-posts/v2/${post['post_type']}/multi-select-values`,
                                                data: {
                                                    s: "{{query}}",
                                                    field: field_id
                                                },
                                                beforeSend: function (xhr) {
                                                    xhr.setRequestHeader('X-WP-Nonce', jsObject['nonce']);
                                                },
                                                callback: {
                                                    done: function (data) {
                                                        return (data || []).map(tag => {
                                                            return {name: tag}
                                                        })
                                                    }
                                                }
                                            }
                                        }
                                    },
                                    display: "name",
                                    templateValue: "{{name}}",
                                    emptyTemplate: function (query) {
                                        const {addNewTagText, tagExistsText} = this.node[0].dataset
                                        if (this.comparedItems.includes(query)) {
                                            return tagExistsText.replace('%s', query)
                                        }
                                        const liItem = jQuery('<li>')
                                        const button = jQuery('<button>', {
                                            class: "button primary",
                                            text: addNewTagText.replace('%s', query),
                                        })
                                        const tag = this.query
                                        button.on("click", function () {
                                            window.Typeahead[typeahead_tags_field_input].addMultiselectItemLayout({name: tag});
                                        })
                                        liItem.append(button);
                                        return liItem;
                                    },
                                    dynamic: true,
                                    multiselect: {
                                        matchOn: ["name"],
                                        data: function () {
                                            return (post[field_id] || []).map(t => {
                                                return {name: t}
                                            })
                                        },
                                        callback: {
                                            onCancel: function (node, item, event) {
                                                // Keep a record of deleted tags
                                                let deleted_items = (field_meta.val()) ? JSON.parse(field_meta.val()) : [];
                                                deleted_items.push(item);
                                                field_meta.val(JSON.stringify(deleted_items));
                                            }
                                        },
                                        href: function (item) {
                                        },
                                    },
                                    callback: {
                                        onClick: function (node, a, item, event) {
                                            event.preventDefault();
                                            this.addMultiselectItemLayout({name: item.name});
                                        },
                                        onResult: function (node, query, result, resultCount) {
                                            let text = TYPEAHEADS.typeaheadHelpText(resultCount, query, result)
                                            jQuery(tr).find(`#${field_id}-result-container`).html(text);
                                        },
                                        onHideLayout: function () {
                                            jQuery(tr).find(`#${field_id}-result-container`).html("");
                                        },
                                        onShowLayout() {
                                        }
                                    }
                                });
                            }

                            break;
                        }
                    }
                });
            };

            /**
             * Refresh report comments text area.
             */

            window.refresh_report_comments = (limit, comments) => {
                let comments_div = jQuery('#form_content_comments_div');
                let current_comments = jQuery('#form_content_current_comments');
                let previous_comments = jQuery('#form_content_previous_comments');

                comments_div.fadeOut('fast', function () {

                    // Clear all comment types.
                    current_comments.val('');
                    previous_comments.empty();

                    // If available, display past comments.
                    if (comments) {
                        let html_comments = '';
                        let counter = 0;
                        comments.forEach(comment => {
                            if (counter++ < limit) { // Enforce comment count limit..!
                                html_comments += `<b>${window.lodash.escape(comment['comment_author'])} @ ${window.lodash.escape(comment['comment_date'])}</b><br>`;
                                html_comments += `${window.lodash.escape(comment['comment_content'])}<hr>`;
                            }
                        });
                        previous_comments.html(html_comments);
                    }

                    // Display comments area.
                    comments_div.fadeIn('fast');
                });
            };

            /**
             * Submit contact details
             */

            jQuery('#content_submit_but').on("click", function () {
                let id = jQuery('#post_id').val();
                let state = jQuery('#post_state').val();

                // Reset error message field
                let error = jQuery('#error');
                error.html('');

                // Sanity check content prior to submission
                if ((!id || String(id).trim().length === 0) && String(state).trim() !== 'new') {
                    error.html('Invalid post id detected!');

                } else {

                    // Build payload accordingly, based on enabled states
                    let payload = {
                        'action': 'get',
                        'parts': jsObject.parts,
                        'post_id': id,
                        'post_state': state,
                        'fields': [],
                        'comments': jQuery('#form_content_current_comments').val()
                    }

                    // Iterate over form fields, capturing values accordingly.
                    jQuery('.form-content-table > tbody > tr').each(function (idx, tr) {
                        let field_id = jQuery(tr).find('#form_content_table_field_id').val();
                        let field_type = jQuery(tr).find('#form_content_table_field_type').val();
                        let field_meta = jQuery(tr).find('#form_content_table_field_meta');

                        let selector = '#' + field_id;
                        switch (field_type) {

                            case 'number':
                            case 'textarea':
                            case 'text':
                            case 'key_select': {

                                payload['fields'].push({
                                    id: field_id,
                                    type: field_type,
                                    value: jQuery(tr).find(selector).val()
                                });

                                break;
                            }

                            case 'communication_channel': {

                                let values = [];
                                jQuery(tr).find('.input-group').each(function () {
                                    values.push({
                                        'key': jQuery(this).find('button').data('key'),
                                        'value': jQuery(this).find('input').val()
                                    });
                                });

                                payload['fields'].push({
                                    id: field_id,
                                    type: field_type,
                                    value: values,
                                    deleted: field_meta.val() ? JSON.parse(field_meta.val()) : []
                                });

                                break;
                            }

                            case 'multi_select': {

                                let options = [];
                                jQuery(tr).find('button').each(function () {
                                    options.push({
                                        'value': jQuery(this).attr('id'),
                                        'delete': jQuery(this).hasClass('empty-select-button')
                                    });
                                });

                                payload['fields'].push({
                                    id: field_id,
                                    type: field_type,
                                    value: options
                                });

                                break;
                            }

                            case 'boolean': {

                                let initial_val = JSON.parse(jQuery(tr).find('#field_initial_state_' + field_id).val());
                                let current_val = jQuery(tr).find(selector).prop('checked');

                                payload['fields'].push({
                                    id: field_id,
                                    type: field_type,
                                    value: current_val,
                                    changed: (initial_val !== current_val)
                                });

                                break;
                            }

                            case 'date': {

                                payload['fields'].push({
                                    id: field_id,
                                    type: field_type,
                                    value: field_meta.val()
                                });

                                break;
                            }

                            case 'tags':
                            case 'location': {

                                let typeahead = window.Typeahead['.js-typeahead-' + field_id];
                                if (typeahead) {
                                    payload['fields'].push({
                                        id: field_id,
                                        type: field_type,
                                        value: typeahead.items,
                                        deletions: field_meta.val() ? JSON.parse(field_meta.val()) : []
                                    });
                                }

                                break;
                            }

                            case 'location_meta': {

                                payload['fields'].push({
                                    id: field_id,
                                    type: field_type,
                                    value: (window.selected_location_grid_meta !== undefined) ? window.selected_location_grid_meta : '',
                                    deletions: field_meta.val() ? JSON.parse(field_meta.val()) : []
                                });

                                break;
                            }

                            default:
                                break;
                        }
                    });

                    // Submit data for post update
                    jQuery('#content_submit_but').prop('disabled', true);

                    jQuery.ajax({
                        type: "POST",
                        data: JSON.stringify(payload),
                        contentType: "application/json; charset=utf-8",
                        dataType: "json",
                        url: jsObject.root + jsObject.parts.root + '/v1/' + jsObject.parts.type + '/update',
                        beforeSend: function (xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', jsObject.nonce)
                        }

                    }).done(function (data) {

                        // If successful, refresh page, otherwise; display error message
                        if (data['success']) {
                            Function(jsObject.submit_success_function)();

                        } else {
                            jQuery('#error').html(data['message']);
                            jQuery('#content_submit_but').prop('disabled', false);
                        }

                    }).fail(function (e) {
                        console.log(e);
                        jQuery('#error').html(e);
                        jQuery('#content_submit_but').prop('disabled', false);
                    });
                }
            });

        </script>
        <?php
        return true;
    }

    public function body() {
        ?>
        <div id="custom-style"></div>
        <div id="wrapper">
            <div class="grid-x">
                <div class="cell center">
                    <h2 id="title"><?php esc_html_e( 'Report Survey Collection', 'disciple-tools-survey-collection' ) ?></h2>
                </div>
            </div>
            <hr>
            <div id="content">
                <div id="assigned_reports_div">
                    <table>
                        <tbody style="border: none;">
                        <tr style="border: none;">
                            <td>
                                <h3><?php esc_html_e( 'Reports', 'disciple-tools-survey-collection' ) ?> [ <span
                                        id="total">0</span> ]</h3>
                            </td>
                            <td style="text-align: right;">
                                <button id="view_all_reports_but"
                                        class="button select-button"
                                        onclick="window.open('<?php echo esc_url_raw( site_url('reports/') ); ?>','_blank');"><?php esc_html_e( 'View All Reports', 'disciple-tools-survey-collection' ) ?></button>
                                <button id="new_report_but"
                                        class="button select-button"><?php esc_html_e( 'New Report', 'disciple-tools-survey-collection' ) ?></button>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <hr>
                    <div class="grid-x api-content-div-style" id="api-content">
                        <span class="list-loading-spinner loading-spinner active"></span>
                        <table class="api-content-table">
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                    <br>
                </div>

                <!-- ERROR MESSAGES -->
                <span id="error" style="color: red;"></span>
                <br>
                <br>

                <!-- POST DETAILS -->
                <h3><span id="report_name"></span></h3>
                <hr>
                <div class="grid-x" id="form-content">
                    <span class="details-loading-spinner loading-spinner"></span>
                    <input id="post_id" type="hidden"/>
                    <input id="post_state" type="hidden"/>
                    <table style="display: none;" class="form-content-table">
                        <thead>
                        <tr>
                            <th style="width: 5%;"></th>
                            <th></th>
                            <th><?php esc_html_e( 'Last Report', 'disciple-tools-survey-collection' ) ?></th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    <div id="form_content_comments_div" style="display: none; min-width: 100%;">
                        <textarea id="form_content_current_comments"
                                  placeholder="<?php esc_html_e( 'Report Comments', 'disciple-tools-survey-collection' ) ?>"></textarea><br>

                        <div id="form_content_previous_comments"></div>
                    </div>
                </div>

                <!-- SUBMIT UPDATES -->
                <button id="content_submit_but" style="display: none; min-width: 100%;" class="button select-button">
                    <?php esc_html_e( 'Submit Update', 'disciple-tools-survey-collection' ) ?>
                </button>

                <!-- FIELD HELP MODAL -->
                <dialog id="report_field_help_dialog">
                    Hello World...
                </dialog>
            </div>
        </div>
        <?php
    }

    /**
     * Register REST Endpoints
     * @link https://github.com/DiscipleTools/disciple-tools-theme/wiki/Site-to-Site-Link for outside of wordpress authentication
     */
    public function add_endpoints() {
        $namespace = $this->root . '/v1';
        register_rest_route(
            $namespace, '/' . $this->type, [
                [
                    'methods'             => 'GET',
                    'callback'            => [ $this, 'endpoint_get' ],
                    'permission_callback' => function ( WP_REST_Request $request ) {
                        $magic = new DT_Magic_URL( $this->root );

                        return $magic->verify_rest_endpoint_permissions_on_post( $request );
                    },
                ],
            ]
        );
        register_rest_route(
            $namespace, '/' . $this->type . '/new_post', [
                [
                    'methods'             => 'GET',
                    'callback'            => [ $this, 'new_post' ],
                    'permission_callback' => function ( WP_REST_Request $request ) {
                        $magic = new DT_Magic_URL( $this->root );

                        return $magic->verify_rest_endpoint_permissions_on_post( $request );
                    },
                ],
            ]
        );
        register_rest_route(
            $namespace, '/' . $this->type . '/post', [
                [
                    'methods'             => 'GET',
                    'callback'            => [ $this, 'get_post' ],
                    'permission_callback' => function ( WP_REST_Request $request ) {
                        $magic = new DT_Magic_URL( $this->root );

                        return $magic->verify_rest_endpoint_permissions_on_post( $request );
                    },
                ],
            ]
        );
        register_rest_route(
            $namespace, '/' . $this->type . '/update', [
                [
                    'methods'             => 'POST',
                    'callback'            => [ $this, 'update_record' ],
                    'permission_callback' => function ( WP_REST_Request $request ) {
                        $magic = new DT_Magic_URL( $this->root );

                        return $magic->verify_rest_endpoint_permissions_on_post( $request );
                    },
                ],
            ]
        );
    }

    public function endpoint_get( WP_REST_Request $request ) {
        $params = $request->get_params();
        if ( ! isset( $params['parts'], $params['action'] ) ) {
            return new WP_Error( __METHOD__, 'Missing parameters', [ 'status' => 400 ] );
        }

        // Sanitize and fetch user id
        $params  = dt_recursive_sanitize_array( $params );
        $user_id = $params['parts']['post_id'];

        // Fetch all assigned posts
        $data = [];
        if ( ! empty( $user_id ) ) {

            // Update logged-in user state as required
            $original_user = wp_get_current_user();
            wp_set_current_user( $user_id );

            // Fetch all assigned posts
            $posts = $this->list_reports( $params['limit'] ?? 5, '-submit_date' );

            // Revert to original user
            if ( ! empty( $original_user ) && isset( $original_user->ID ) ) {
                wp_set_current_user( $original_user->ID );
            }

            // Iterate and return valid posts
            if ( ! empty( $posts ) && isset( $posts['posts'], $posts['total'] ) ) {
                $data['total'] = count( $posts['posts'] );
                foreach ( $posts['posts'] ?? [] as $post ) {
                    $data['posts'][] = [
                        'id'   => $post['ID'],
                        'name' => $post['name']
                    ];
                }
            }
        }

        return $data;
    }

    private function list_reports( $limit, $sort ): array {
        return DT_Posts::list_posts( 'reports', [
            'limit'  => $limit,
            'sort'   => $sort,
            'fields' => [
                [
                    'assigned_to' => [ 'me' ]
                ],
                'status' => [
                    'new',
                    'unassigned',
                    'assigned',
                    'active'
                ]
            ]
        ] );
    }

    public function new_post( WP_REST_Request $request ) {
        $params = $request->get_params();
        if ( ! isset( $params['parts'], $params['action'], $params['link_obj_id'] ) ) {
            return new WP_Error( __METHOD__, 'Missing parameters', [ 'status' => 400 ] );
        }

        // Capture fields to be returned, with name field topping the list.
        $fields          = self::build_meta_report_survey_collection_fields();
        $field_settings  = DT_Posts::get_post_field_settings( 'reports', false );
        $link_obj        = Disciple_Tools_Bulk_Magic_Link_Sender_API::fetch_option_link_obj( $params['link_obj_id'] );
        $fields_response = [];
        foreach ( $fields as $field ) {
            if ( self::is_field_enabled( $link_obj, $field['id'] ) ) {

                // Generate field response data, such as display element html.
                ob_start();
                render_field_for_display( $field['id'], $field_settings, null, true );
                $rendered_field_html = ob_get_contents();
                ob_end_clean();

                $fields_response[] = [
                    'id'   => $field['id'],
                    'type' => $field_settings[ $field['id'] ]['type'] ?? '',
                    'html' => $rendered_field_html
                ];
            }
        }

        // Package and return filtered fields.
        return [
            'fields' => $fields_response,
            'last'   => $this->list_reports( 1, '-submit_date' )['posts'][0] ?? null
        ];
    }

    public function get_post( WP_REST_Request $request ) {
        $params = $request->get_params();
        if ( ! isset( $params['post_id'], $params['parts'], $params['action'], $params['link_obj_id'], $params['comment_count'] ) ) {
            return new WP_Error( __METHOD__, 'Missing parameters', [ 'status' => 400 ] );
        }

        // Sanitize and fetch user id.
        $params  = dt_recursive_sanitize_array( $params );
        $user_id = $params['parts']['post_id'];

        // Update logged-in user state as required.
        $original_user = wp_get_current_user();
        wp_set_current_user( $user_id );

        // Fetch corresponding reports post record.
        $response = [];
        $post     = DT_Posts::get_post( 'reports', $params['post_id'], false );
        if ( ! empty( $post ) && ! is_wp_error( $post ) ) {

            // Now, source corresponding fields for given link object.
            $fields         = self::build_meta_report_survey_collection_fields();
            $field_settings = DT_Posts::get_post_field_settings( 'reports', false );
            $link_obj       = Disciple_Tools_Bulk_Magic_Link_Sender_API::fetch_option_link_obj( $params['link_obj_id'] );

            // Only process enabled fields.
            $fields_response = [];
            foreach ( $fields as $field ) {
                if ( self::is_field_enabled( $link_obj, $field['id'] ) ) {

                    // Generate field response data, such as display element html.
                    ob_start();
                    render_field_for_display( $field['id'], $field_settings, $post, true );
                    $rendered_field_html = ob_get_contents();
                    ob_end_clean();

                    $fields_response[] = [
                        'id'   => $field['id'],
                        'type' => $field_settings[ $field['id'] ]['type'] ?? '',
                        'html' => $rendered_field_html
                    ];
                }
            }

            // Return findings...
            $response['post']     = $post;
            $response['fields']   = $fields_response;
            $response['comments'] = DT_Posts::get_post_comments( 'reports', $params['post_id'], false, 'all', [ 'number' => $params['comment_count'] ] );
            $response['last']     = $this->list_reports( 1, '-submit_date' )['posts'][0] ?? null;
            $response['success']  = true;

        } else {
            $response['success'] = false;
        }

        // Revert to original user
        if ( ! empty( $original_user ) && isset( $original_user->ID ) ) {
            wp_set_current_user( $original_user->ID );
        }

        return $response;
    }

    private function is_field_enabled( $link_obj, $field_id ): bool {
        $enabled = true;
        foreach ( $link_obj->type_fields ?? [] as $field ) {
            if ( $field->id == $field_id ) {
                $enabled = $field->enabled;
            }
        }

        return $enabled;
    }

    public function update_record( WP_REST_Request $request ) {
        $params = $request->get_params();
        if ( ! isset( $params['post_id'], $params['post_state'], $params['parts'], $params['action'], $params['fields'] ) ) {
            return new WP_Error( __METHOD__, 'Missing core parameters', [ 'status' => 400 ] );
        }

        // Sanitize and fetch user id
        $params = dt_recursive_sanitize_array( $params );

        // Update logged-in user state, if required
        if ( ! is_user_logged_in() ) {
            $this->update_user_logged_in_state();
        }

        $updates = [];

        // First, capture and package incoming DT field values
        foreach ( $params['fields'] ?? [] as $field ) {
            switch ( $field['type'] ) {
                case 'number':
                case 'textarea':
                case 'text':
                case 'key_select':
                case 'date':
                    $updates[ $field['id'] ] = $field['value'];

                    // Ensure post name is also set to submission date
                    if ( ( $field['type'] == 'date' ) && ( $field['id'] == 'submit_date' ) ) {
                        $dt = new DateTime();
                        $dt->setTimezone( new DateTimeZone( 'UTC' ) );
                        $dt->setTimestamp( $field['value'] );
                        $updates['name'] = $dt->format( 'F j, Y' );
                    }
                    break;

                case 'boolean':

                    // Only update if there has been a state change!
                    if ( $field['changed'] ) {
                        $updates[ $field['id'] ] = $field['value'] === 'true';
                    }
                    break;

                case 'communication_channel':
                    $updates[ $field['id'] ] = [];

                    // First, capture additions and updates
                    foreach ( $field['value'] ?? [] as $value ) {
                        $comm          = [];
                        $comm['value'] = $value['value'];

                        if ( $value['key'] !== 'new' ) {
                            $comm['key'] = $value['key'];
                        }

                        $updates[ $field['id'] ][] = $comm;
                    }

                    // Next, capture deletions
                    foreach ( $field['deleted'] ?? [] as $delete_key ) {
                        $updates[ $field['id'] ][] = [
                            'delete' => true,
                            'key'    => $delete_key
                        ];
                    }
                    break;

                case 'multi_select':
                    $options = [];
                    foreach ( $field['value'] ?? [] as $option ) {
                        $entry          = [];
                        $entry['value'] = $option['value'];
                        if ( $option['delete'] ) {
                            $entry['delete'] = true;
                        }
                        $options[] = $entry;
                    }
                    if ( ! empty( $options ) ) {
                        $updates[ $field['id'] ] = [
                            'values' => $options
                        ];
                    }
                    break;

                case 'location':
                    $locations = [];
                    foreach ( $field['value'] ?? [] as $location ) {
                        $entry          = [];
                        $entry['value'] = $location['ID'];
                        $locations[]    = $entry;
                    }

                    // Capture any incoming deletions
                    foreach ( $field['deletions'] ?? [] as $location ) {
                        $entry           = [];
                        $entry['value']  = $location['ID'];
                        $entry['delete'] = true;
                        $locations[]     = $entry;
                    }

                    // Package and append to global updates
                    if ( ! empty( $locations ) ) {
                        $updates[ $field['id'] ] = [
                            'values' => $locations
                        ];
                    }
                    break;

                case 'location_meta':
                    $locations = [];

                    // Capture selected location, if available; or prepare shape
                    if ( ! empty( $field['value'] ) && isset( $field['value'][ $field['id'] ] ) ) {
                        $locations[ $field['id'] ] = $field['value'][ $field['id'] ];

                    } else {
                        $locations[ $field['id'] ] = [
                            'values' => []
                        ];
                    }

                    // Capture any incoming deletions
                    foreach ( $field['deletions'] ?? [] as $id ) {
                        $entry                                 = [];
                        $entry['grid_meta_id']                 = $id;
                        $entry['delete']                       = true;
                        $locations[ $field['id'] ]['values'][] = $entry;
                    }

                    // Package and append to global updates
                    if ( ! empty( $locations[ $field['id'] ]['values'] ) ) {
                        $updates[ $field['id'] ] = $locations[ $field['id'] ];
                    }
                    break;

                case 'tags':
                    $tags = [];
                    foreach ( $field['value'] ?? [] as $tag ) {
                        $entry          = [];
                        $entry['value'] = $tag['name'];
                        $tags[]         = $entry;
                    }

                    // Capture any incoming deletions
                    foreach ( $field['deletions'] ?? [] as $tag ) {
                        $entry           = [];
                        $entry['value']  = $tag['name'];
                        $entry['delete'] = true;
                        $tags[]          = $entry;
                    }

                    // Package and append to global updates
                    if ( ! empty( $tags ) ) {
                        $updates[ $field['id'] ] = [
                            'values' => $tags
                        ];
                    }
                    break;
            }
        }

        // Specific new report requirements....
        if ( $params['post_state'] == 'new' ) {

            // Ensure a valid name field is present
            if ( empty( $updates['name'] ) ) {
                return [
                    'success' => false,
                    'message' => __( 'Please ensure a valid report name is specified!', 'disciple_tools' )
                ];
            }

            // By default, assign to user and default to now as submission date
            $updates['assigned_to'] = 'user-' . $params['parts']['post_id'];
            $updates['submit_date'] = time();
        }

        // Update/Create post record accordingly, based on incoming flags.
        $updated_post = ( $params['post_state'] == 'new' ) ? DT_Posts::create_post( 'reports', $updates, false, false ) : DT_Posts::update_post( 'reports', $params['post_id'], $updates, false, false );
        if ( empty( $updated_post ) || is_wp_error( $updated_post ) ) {
            return [
                'success' => false,
                'message' => __( 'Unable to update report record details!', 'disciple_tools' )
            ];
        }

        // Add any available comments
        if ( isset( $params['comments'] ) && ! empty( $params['comments'] ) ) {
            $updated_comment = DT_Posts::add_post_comment( $updated_post['post_type'], $updated_post['ID'], $params['comments'], 'comment', [], false );
            if ( empty( $updated_comment ) || is_wp_error( $updated_comment ) ) {
                return [
                    'success' => false,
                    'message' => 'Unable to add comment to contact record details!'
                ];
            }
        }

        // Finally, return successful response
        return [
            'success' => true,
            'message' => ''
        ];
    }

    private function update_user_logged_in_state() {
        wp_set_current_user( 0 );
        $current_user = wp_get_current_user();
        $current_user->add_cap( 'magic_link' );
        $current_user->display_name = __( 'Report Survey Collection', 'disciple_tools' );
    }
}
Disciple_Tools_Survey_Collection_Magic_User_App::instance();
