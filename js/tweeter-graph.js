<script type='text/javascript'>//<![CDATA[
$(function () {
    // Create the chart
    $('#chartcontainer<!--type-->').highcharts({
        chart: {
            type: 'bar'
        },
        title: {
            text: '<!--title-->'
        },
        subtitle: {
            text: '<!--subtitle-->'
        },
        xAxis: {
            type: 'category'
        },
        yAxis: {
            title: {
                text: '<!--yaxis-->'
            }
        },
        legend: {
            enabled: false
        },
        plotOptions: {
            series: {
                borderWidth: 0,
                dataLabels: {
                    enabled: true,
                    format: '{point.y:,.0f}'
                },
                cursor: 'pointer',
                point: {
                    events: {
                        click: function (event) {
                              GetDetails(this.options.sec,'fetch_tweeters.php?profile=1&i='+this.options.rank+'&sec='+this.options.sec+'&user='+this.options.name+'&table='+this.options.case);
                        } //this.options.sec
                    }
                }
             }
        },

        tooltip: {
          enabled: false
        },

        series:
          [
           {
            name: '<!--name-->',
            colorByPoint: true,
            data: [
              <!--data-->
                  ]
           }
         ],
        drilldown:
            {
              series:
                    [
                      <!--drilldowns-->
                    ]
            }
    });
});
//]]>
</script>
