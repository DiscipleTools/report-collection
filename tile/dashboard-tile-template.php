<div class="tile-header">
    <?php echo esc_html( $tile->label ) ?>
    <div style="display: inline-block" class="stats-spinner loading-spinner active"></div>
</div>
<div class="tile-body tile-body--center">
    <div>
        <p style="text-align: center; display: none" id="empty_survey_collection_stats">
            <strong><?php esc_html_e( 'No data to show yet. You have no active reports', 'disciple-tools-survey-collection' ) ?></strong>
        </p>
        <div id="survey_collection_stats_chart"
             style="height:400px; width;200px; padding-left: 10px; padding-right: 10px"></div>
    </div>
</div>
