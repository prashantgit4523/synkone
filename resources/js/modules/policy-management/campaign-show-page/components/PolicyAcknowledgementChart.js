import React, {Fragment} from 'react';
import Chart from 'react-apexcharts'

function PolicyAcknowledgementChart(props) {
    const {completedAcknowledgements, totalAcknowledgements,campaign} = props
    let completedAcknowledgementPercentage = 0;
    /* calculation of completedAcknowledgementPercentage*/
    if (completedAcknowledgements && totalAcknowledgements) {
        completedAcknowledgementPercentage = (completedAcknowledgements * 100) / totalAcknowledgements;
    }

    const  options = {
        colors: ["#f7b84b"],

        plotOptions: {
            radialBar: {
                hollow: {
                    margin: 10,
                    size: "60%"
                },

                dataLabels: {
                    name: {
                        show: false
                    },
                    value: {
                        show: true,
                        fontSize: '14px',
                        offsetX: 0,
                        offsetY: 0,
                        formatter: function (val) {
                            return completedAcknowledgements
                        }
                    },
                }
            }
        },

        stroke: {
            lineCap: "round",
        },
        labels: [""]
    };
    const display_title = campaign.campaign_type == 'awareness-campaign' ? "Completed" : "Acknowledged"
    return (
        <Fragment>
            <h4 className="header-title text-center mb-3">{display_title}</h4>
            <Chart options={options} series={[completedAcknowledgementPercentage]} type="radialBar"  height="150" width="100%"></Chart>
        </Fragment>
    );
}

export default PolicyAcknowledgementChart;
