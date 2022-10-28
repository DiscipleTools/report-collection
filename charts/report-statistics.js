(function () {
  "use strict";
  jQuery(document).ready(function () {
    // expand the current selected menu
    jQuery('#metrics-sidemenu').foundation('down', jQuery(`#${window.wp_js_object.base_slug}-menu`));
    show_metrics();
  });

  function show_metrics() {
    let localizedObject = window.wp_js_object; // change this object to the one named in ui-menu-and-enqueue.php
    let translations = localizedObject.translations;
    let chartDiv = jQuery('#chart'); // retrieves the chart div in the metrics page

    chartDiv.empty().html(`
      <span class="section-header">${localizedObject.translations.title}</span><br>
      <span class="section-subheader">${localizedObject.translations.sub_title}</span><br>
      <hr style="max-width:100%;">
      <div id="chartdiv" style="min-width: 75%; max-width: 75%; min-height: 500px; margin: auto;"></div>
      <hr style="max-width:100%;">
      <button type="button" onclick="refresh_api_call()" class="button" id="refresh_button">${translations["refresh"]}</button>
      <div id="refresh_spinner" style="display: inline-block" class="loading-spinner"></div>
    `);

    // Display statistics
    jQuery('#chartdiv').empty().html(render_metrics_dashboard_stats_html(localizedObject.stats));
  }

  window.render_metrics_dashboard_stats_html = function (stats) {
    let html = `<div style="display: flex; flex-flow: row wrap; justify-content: center; overflow: auto;">`;

    // Iterate and display stats
    if (stats) {
      jQuery.each(stats, function (idx, stat) {
        if (stat['value'], stat['label']) {
          html += `
          <div style="margin-right: 30px; flex: 1 1 0;">
            <div>
                <span style="font-size: 60px; font-weight: bold; color: blue;">${window.lodash.escape(stat['value'])}</span>
            </div>
            <div>${window.lodash.escape(stat['label'])}</div>
          </div>
          `;
        }
      });
    }

    html += `</div>`;

    return html;
  }

  window.refresh_api_call = function refresh_api_call() {

    let localizedObject = window.wp_js_object; // change this object to the one named in ui-menu-and-enqueue.php
    $('#sample_spinner').addClass("active");

    let data = {};
    return jQuery.ajax({
      type: "POST",
      data: JSON.stringify(data),
      contentType: "application/json; charset=utf-8",
      dataType: "json",
      url: `${localizedObject.rest_endpoints_base}/refresh`,
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', localizedObject.nonce);
      },
    })
      .done(function (data) {
        $('#refresh_spinner').removeClass("active");
        console.log('success');
        console.log(data);

        // Refresh stats....
        let chart_div = jQuery('#chartdiv');
        chart_div.fadeOut('fast', function () {
          chart_div.empty().html(render_metrics_dashboard_stats_html(data));
          chart_div.fadeIn('fast');
        });

      })
      .fail(function (err) {
        $('#refresh_spinner').removeClass("active");
        console.log("error");
        console.log(err);
      })
  }
})();
