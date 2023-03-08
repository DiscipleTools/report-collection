<div class="tile-header">
    <?php echo esc_html( $tile->label ) ?>
    <div style="display: inline-block" class="stats-spinner loading-spinner"></div>
</div>
<div class="tile-subheader"><?php esc_html_e( 'All Time', 'disciple-tools-survey-collection' ) ?></div>
<div class="tile-body tile-body--center">
    <div>
        <p><?php esc_html_e( 'Reports with submission dates set in the future are not included within metric calculations.', 'disciple-tools-survey-collection' ) ?></p>
        <p style="text-align: center; display: none" id="empty_survey_collection_stats">
            <strong><?php esc_html_e( 'No data to show yet. You have no active reports', 'disciple-tools-survey-collection' ) ?></strong>
        </p>
        <?php

        // Calculate statistics for reports post type.
        $raw_stats = apply_filters( 'dt_after_get_post_fields_filter', [], 'reports' );

        // Package any generated statistics.
        $packaged_stats = [];
        $stats          = [
            [
                'key'   => 'stats_prayers_all_time',
                'label' => __( 'Total Prayers', 'disciple-tools-survey-collection' ),
                'section' => 'leading'
            ],
            [
                'key'   => 'stats_shares_all_time',
                'label' => __( 'Total Shares', 'disciple-tools-survey-collection' ),
                'section' => 'leading'
            ],
            [
                'key'   => 'stats_invites_all_time',
                'label' => __( 'Total Invites', 'disciple-tools-survey-collection' ),
                'section' => 'leading'
            ],
            [
                'key'   => 'stats_new_baptisms_all_time',
                'label' => __( 'Total New Baptisms', 'disciple-tools-survey-collection' ),
                'section' => 'lagging'
            ],
            [
                'key'   => 'stats_new_groups_all_time',
                'label' => __( 'Total New Groups', 'disciple-tools-survey-collection' ),
                'section' => 'lagging'
            ],
            [
                'key'   => 'stats_active_groups',
                'label' => __( 'Current Active Groups', 'disciple-tools-survey-collection' ),
                'section' => 'lagging'
            ],
            [
                'key' => 'stats_accountability_days_since',
                'label' => __( 'Days Since Last Reported Accountability', 'disciple-tools-survey-collection' ),
                'section' => 'lagging'
            ]
        ];

        // Identify any other metric fields.
        $other_metric_fields = apply_filters( 'survey_collection_identify_other_metric_fields', [], 'reports', [ 'number' ], [ 'tracking' ], [
            'status',
            'assigned_to',
            'submit_date',
            'rpt_start_date',
            'shares',
            'prayers',
            'invites',
            'new_baptisms',
            'new_groups',
            'active_groups'
        ] );

        // Capture other metric fields within overall stats.
        if ( !empty( $other_metric_fields ) ){
            foreach ( $other_metric_fields as $field_key => $field ){
                if ( isset( $field['name'] ) ){
                    $key = 'stats_' . $field_key . '_all_time';
                    $stats[] = [
                        'key' => $key,
                        'label' => sprintf( __( 'Total %s', 'disciple-tools-survey-collection' ), $field['name'] )
                    ];
                }
            }
        }

        // Package final stats shape and render html display.
        foreach ( $stats as $stat ) {
            if ( isset( $raw_stats[ $stat['key'] ] ) ) {
                $packaged_stats[] = [
                    'key'   => $stat['key'],
                    'label' => $stat['label'],
                    'section' => $stat['section'] ?? 'lagging',
                    'value' => $raw_stats[ $stat['key'] ]
                ];
            }
        }

        do_action( 'survey_collection_metrics_dashboard_stats_html', $packaged_stats );
        ?>

        <br><br>
        <a href="<?php echo esc_url( site_url() . '/metrics/disciple-tools-survey-collection-metrics/report_stats' ) ?>"
           class="button select-button"
           style="min-width: 100%;"><?php esc_html_e( 'See Global Dashboard', 'disciple-tools-survey-collection' ) ?></a>

    </div>
</div>
