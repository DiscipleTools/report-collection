(function ($) {
  window.dt_dashboard.onAdd('dt_survey_collection_dashboard_tile', function (context) {
    am4core.options.autoSetClassName = true;

    const options = {
      type: 'GET',
      contentType: 'application/json; charset=utf-8',
      dataType: 'json',
      url: `${window.wpApiShare.root}disciple-tools-survey-collection/v1/stats`,
      beforeSend: xhr => {
        xhr.setRequestHeader('X-WP-Nonce', window.wpApiShare.nonce);
      }
    }

    $.ajax(options).then(resp => {
      $(context.element).find('.stats-spinner').removeClass('active');
      survey_collection_stats(resp);
    });

    function survey_collection_stats(data) {
      am4core.options.autoSetClassName = true;
      am4core.useTheme(am4themes_animated);

      let chart = am4core.create("survey_collection_stats_chart", am4charts.PieChart);
      chart.hiddenState.properties.opacity = 0; // this makes initial fade in effect

      // remove empty values
      window.lodash.pullAllBy(data, [{value: 0}], "value");

      // update core chart data
      chart.data = data;

      // display empty message if needed
      if (window.lodash.isEmpty(chart.data)) {
        $(context.element).find('#empty_survey_collection_stats').show();
      }

      // render chart
      chart.radius = am4core.percent(70);
      chart.innerRadius = am4core.percent(40);
      chart.startAngle = 180;
      chart.endAngle = 360;

      let series = chart.series.push(new am4charts.PieSeries());
      series.dataFields.value = "value";
      series.dataFields.category = "label";

      series.slices.template.cornerRadius = 10;
      series.slices.template.innerCornerRadius = 7;
      series.slices.template.draggable = true;
      series.slices.template.inert = true;
      series.alignLabels = false;
      series.slices.minHorizontalGap = 0;

      series.hiddenState.properties.startAngle = 90;
      series.hiddenState.properties.endAngle = 90;

      series.labels.template.text = "{value}";
      series.labels.template.wrap = true;
      series.labels.template.maxWidth = 90;

      series.colors.list = [
        am4core.color("#C7E3FF"),
        am4core.color("#B7D6F3"),
        am4core.color("#A8C9E8"),
        am4core.color("#99BDDD"),
        am4core.color("#8AB0D2"),
        am4core.color("#7BA4C7"),
        am4core.color("#6C97BC"),
        am4core.color("#5D8BB1"),
        am4core.color("#4E7EA6"),
        am4core.color("#3F729B"),
      ];

      chart.legend = new am4charts.Legend();
      chart.legend.valueLabels.template.text = "";
      chart.legend.labels.template.text = "[font-size: 10px]{category}: {value}[/]";
    }

  });
})(window.jQuery)
