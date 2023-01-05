<style>
    .text-center {
        text-align: center !important;
    }

    .list-group {
        display: flex;
        flex-direction: column;
        padding-left: 0;
        margin-bottom: 0;
        border-radius: 0;
    }

    .list-group-item {
        position: relative;
        display: block;
        padding: 0.75rem 1.25rem;
        color: #323a46;
        background-color: #fff;
        border: 1px solid rgba(0, 0, 0, 0.125);
    }

    .list-group-item:first-child {
        border-top-left-radius: inherit;
        border-top-right-radius: inherit;
    }

    .list-group-flush > .list-group-item {
        border-width: 0 0 1px;
    }

    .float-start {
        float: left !important;
    }

    .float-end {
        float: right !important;
    }
    .badge {
        display: inline-block;
        padding: 0.25em 0.4em;
        font-weight: 700;
        line-height: 1;
        color: #fff;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: 0.25rem;
    }

    .rounded-pill {
        border-radius: 50rem !important;
    }
    table, th, td {
        border: 1px solid black !important;
        border-collapse: collapse;
    }

    table.report-version-table td {
        height: 55px;
        vertical-align: bottom;
        padding: 0 0 0 7px;
    }
    
    .list-group-flush > .list-group-item:last-child {
        border-bottom-width: 1px !important;
    }    

    .bg-color-primary {
        background-color: rgba(102, 88, 221, 1) !important;
    }

    .bg-color-danger {
        background-color: rgba(241, 85, 108, 1) !important;
    }

    .bg-color-success {
        background-color: green !important;
    }

    .container {
        width: 900px;
    }

    /* custom css */
</style>

<div class="container">
    <div>
        <h3 class="text-center mb-3">Vendor Maturity</h3>
        <ul class="list-group list-group-flush">
            @foreach($levels as $level)
                <li class="list-group-item">
                    <strong>{{ $level['name'] }}</strong>
                    <span class="badge bg-color-primary rounded-pill float-end">
                        {{ $level['count'] }}
                        </span>
                </li>
            @endforeach
        </ul>
    </div>
    <div>
        <h3 class="text-center mb-3">Vendors on the basis of maturity</h3>
        <canvas id="risk-by-severity-chart" width="400" height="150"></canvas>
    </div>


    <div style="page-break-after: always !important;">
    </div>

    <!-- TOP RISK TABLE STARTS HERE -->
    <div class="high-effect-risktable">
        <h3 class="text-center pt-5">Top Vendors</h3>
        <table style="text-align:center; width:100%;">
            <thead>
            <tr class="text-center">
                <th scope="col">Vendor Name</th>
                <th scope="col">Score</th>
                <th scope="col">Maturity</th>
                <th scope="col">Status</th>
                <th scope="col">Contact Name</th>
            </tr>
            </thead>

            <tbody>
            @foreach($top_vendors as $vendor)
                <!-- risk status -->
                @php
                    if($vendor->status == "active"){
                        $status = '<span class="badge bg-color-success badge-pill">Active</span>';
                    } else {
                    $status = '<span class="badge bg-color-danger badge-pill">Disabled</span>';
                    }
                @endphp
                <tr class="text-center">
                    <td>{{ $vendor->name }}</td>
                    <td>{!! $vendor->score !!}</td>
                    <td>{{ "Level " . $vendor->level }}</td>
                    <td>{!! $status !!}</td>
                    <td>{{ $vendor->contact_name }}</td>
                </tr>
            @endforeach
            @if(count($top_vendors) == 0)
                <tr>
                    <td colspan="7" class="text-center">No vendors found</td>
                </tr>
            @endif
            </tbody>
        </table>
    </div>

    @if(count($data))
        <div style="margin-bottom:10px; margin-top:15px;">
            <h3 style="margin-bottom: -10px;">Result for {{ $project->name }}</h3>
            <ul style="list-style-type: none;padding-left: 0;">
                <li style="padding: 5px 0;"><strong>Start Date: </strong>{{ $project->launch_date }}</li>
                <li style="padding: 5px 0;"><strong>Due date: </strong>{{ $project->due_date }}
                </li>
                <li style="padding: 5px 0;"><strong>Vendor: </strong>{{ $project->vendor->name }}
                </li>
            </ul>
        </div>

        <div class="high-effect-risktable">
            <h3  class="text-center" style="padding-top: 10px;">Questionnaire Details</h3>
            <table style="text-align:center; width:100%;" class="table questionnair">
                <thead>
                <tr>
                    <th>Question</th>
                    <th>Answer</th>
                </tr>
                </thead>

                <tbody>
                @foreach($data as $question)
                    <tr>
                        <td>{{ $question->text }}</td>
                        <td>{{ isset($question->single_answer) ? $question->single_answer->answer : 'Not answered yet.' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<script src="{{ asset('assets/libs/chart-js/Chart.bundle.min.js') }}"></script>
<script src="{{ asset('assets/libs/chart-js/chartjs-plugin-datalabels-v0.7.0.js') }}"></script>
<script src="{{ asset('assets/libs/chart-js/chartjs-plugin-labels.js') }}"></script>

<script type="text/javascript">
    'use strict';
    (function (setLineDash) {
        CanvasRenderingContext2D.prototype.setLineDash = function () {
            if (!arguments[0].length) {
                arguments[0] = [1, 0];
            }
            // Now, call the original method
            return setLineDash.apply(this, arguments);
        };
    })(CanvasRenderingContext2D.prototype.setLineDash);
    Function.prototype.bind = Function.prototype.bind || function (thisp) {
        var fn = this;
        return function () {
            return fn.apply(thisp, arguments);
        };
    };

    Chart.pluginService.register({
        beforeDraw: function (chart) {
            if (chart.config.options.elements.center) {
                var width = chart.chart.width,
                    height = chart.chart.height,
                    ctx = chart.chart.ctx;

                ctx.restore();
                ctx.font = "em sans-serif";
                ctx.textBaseline = "middle";

                var text = chart.config.options.elements.center.text,
                    textX = Math.round((width - ctx.measureText(text).width) / 2),
                    textY = height / (chart.config.options.elements.center.textY || 2);

                ctx.fillText(text, textX, textY);
                ctx.save();
            }
        }
    });
    // chart js global config

    function drawGraphs() {
        /* Risk on the basis of severity */
        var totalCount = @json($levels).
        reduce(function (acc, val) {
            return acc + val['count'];
        }, 0);

        var riskBySeverityChart = new Chart(document.getElementById("risk-by-severity-chart"), {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: totalCount > 0 ? @json((array_map(function($level) {
                        return $level['count'];
                    }, $levels)) ) : [100],
                    backgroundColor: totalCount > 0 ? @json((array_map(function($level) {
                        return $level['color'];
                    }, $levels)) ) : ['#f5f6f8'],
                }],
                labels: totalCount > 0 ? @json((array_map(function($level) {
                        return $level['name'];
                    }, $levels)) ) : [],
            },
            options: {
                cutoutPercentage: 70,
                title: {
                    display: false
                },
                legend: {
                    display: true
                },
                plugins: {
                    // Change options for ALL labels of THIS CHART
                    labels: totalCount > 0 ? {
                        render: function (args) {
                            return args.value;
                        },
                        fontColor: '#000',
                    } : false,
                    datalabels: false,
                },
                centerText: "Total : " + totalCount
            }
        });
    };

    window.onload = function () {
        drawGraphs();
    }
</script>
