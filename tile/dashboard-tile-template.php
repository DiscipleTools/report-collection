<div class="tile-header">
    <?php echo esc_html( $tile->label ) ?>
    <div style="display: inline-block" class="stats-spinner loading-spinner"></div>
</div>
<div class="tile-body tile-body--center">
    <div>
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
                'key'   => 'stats_new_baptisms_all_time',
                'label' => __( 'Total Assigned New Baptisms', 'disciple-tools-survey-collection' )
            ],
            [
                'key'   => 'stats_new_groups_all_time',
                'label' => __( 'Total Assigned New Groups', 'disciple-tools-survey-collection' )
            ],
            [
                'key'   => 'stats_shares_all_time',
                'label' => __( 'Total Assigned Shares', 'disciple-tools-survey-collection' )
            ],
            [
                'key'   => 'stats_prayers_all_time',
                'label' => __( 'Total Assigned Prayers', 'disciple-tools-survey-collection' )
            ],
            [
                'key'   => 'stats_invites_all_time',
                'label' => __( 'Total Assigned Invites', 'disciple-tools-survey-collection' )
            ],
            [
                'key'   => 'stats_active_groups',
                'label' => __( 'Current Assigned Active Groups', 'disciple-tools-survey-collection' )
            ]
        ];

        foreach ( $stats as $stat ) {
            if ( isset( $raw_stats[ $stat['key'] ] ) ) {
                $packaged_stats[] = [
                    'key'   => $stat['key'],
                    'label' => $stat['label'],
                    'value' => $raw_stats[ $stat['key'] ]
                ];
            }
        }

        do_action( 'survey_collection_metrics_dashboard_stats_html', $packaged_stats );
        ?>

    </div>
</div>
