(function () {
  "use strict";
  jQuery(document).ready(function () {
    // expand the current selected menu
    jQuery('#metrics-sidemenu').foundation('down', jQuery(`#${window.wp_js_object.base_slug}-menu`));
    show_metrics();
  });

  var chart = null;

  function show_metrics() {
    let localizedObject = window.wp_js_object; // change this object to the one named in ui-menu-and-enqueue.php
    let translations = localizedObject.translations;
    let chartDiv = jQuery('#chart'); // retrieves the chart div in the metrics page

    chartDiv.empty().html(`
      <span class="section-header">${localizedObject.translations.title}</span>
      <hr style="max-width:100%;">
      <div id="chartdiv" style="min-width: 100%; min-height: 500px;"></div>
      <hr style="max-width:100%;">
      <button type="button" onclick="refresh_api_call()" class="button" id="refresh_button">${translations["refresh"]}</button>
      <div id="refresh_spinner" style="display: inline-block" class="loading-spinner"></div>
    `);

    // Create chart instance
    chart = am4core.create("chartdiv", am4charts.PieChart);

    // Add data
    chart.data = localizedObject.stats;

    // Add and configure Series
    var pieSeries = chart.series.push(new am4charts.PieSeries());
    pieSeries.dataFields.value = "value";
    pieSeries.dataFields.category = "label";
  }

  window.refresh_api_call = function refresh_api_call(button_data) {

    let localizedObject = window.wp_js_object; // change this object to the one named in ui-menu-and-enqueue.php
    let button = jQuery('#sample_button');
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

        // Refresh chart....
        chart.data = data;
        chart.validateData();

      })
      .fail(function (err) {
        $('#refresh_spinner').removeClass("active");
        console.log("error");
        console.log(err);
      })
  }
})();
