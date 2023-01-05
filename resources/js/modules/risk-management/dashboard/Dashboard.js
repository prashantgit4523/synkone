import React, { useEffect, useState, useRef } from "react";
import { useDispatch, useSelector } from "react-redux";
import Chart from "react-apexcharts";
import AppLayout from "../../../layouts/app-layout/AppLayout";
import fileDownload from "js-file-download";
import DepartmentFilter from "./components/department-filter/DepartmentFilter";
import { fetchDashboardData } from "../../../store/actions/risk-management/dashboard";
import "./styles/style.scss";
import { useDidMountEffect } from "../../../custom-hooks";
import { useStateIfMounted } from "use-state-if-mounted";
import ProjectFilter from "./components/project-filter/ProjectFilter";
import RiskItemsSection from "../risk-register/components/RiskItemsSection";
import RiskRegisterFilters from "../risk-register/components/RiskRegisterFilters";
import Flatpickr from "react-flatpickr";
import "flatpickr/dist/themes/light.css";
import ShortcutButtonsPlugin from "shortcut-buttons-flatpickr";
import "shortcut-buttons-flatpickr/dist/themes/light.min.css";
import moment from 'moment/moment';
import ReactTooltip from "react-tooltip";

function Dashboard(props) {
    const [searchTerm, setSearchTerm] = useState("");
    const [loaded, setLoaded] = useStateIfMounted(false);
    const [risks, setRisks] = useState([]);
    const [riskCountWithinRiskLevels, setRiskCountWithinRiskLevels] = useStateIfMounted([]);
    const [riskSeverityChart, setRiskSeverityChart] = useState(null);
    const [riskCloseStatusChart, setRiskCloseStatusChart] = useState(null);
    const [riskByCategory, setRiskByCategory] = useState([]);
    const projectFilterRef = useRef(null);
    const flatPickrRef = useRef(null);
    const { firstRiskDate } = props;
    const today = moment(new Date()).format('YYYY-MM-DD');
    const todayChangedFormat = moment(new Date()).format('DD-MM-YYYY');
    const [dateToFilter, setDateToFilter] = useState(moment(new Date()).format('YYYY-MM-DD'));
    const [filters, setFilters] = useState({
        search_term: "",
        only_incomplete: false,
    });
    const handleTermChange = (e) => setSearchTerm(e.target.value);
    const handleCheck = () => setFilters({ ...filters, only_incomplete: !filters.only_incomplete });    
    const handleUpdateRiskStatus = (riskId, is_complete) => {
        setRisks(risks.map(r => r.id !== riskId ? r : ({...r, is_complete})))
    };
    const appDataScope = useSelector(
        (state) => state.appDataScope.selectedDataScope.value
    );
    const selectedDepartments = useSelector(
        (store) =>
            store.commonReducer.departmentFilterReducer.selectedDepartment
    );
    const { selectedProjects } = useSelector(
        (store) => store.riskReducer.projectFilterReducer
    );
    const dispatch = useDispatch();
    const departmentFilterRef = useRef(null);

    const [selectedDate, setSelectedDate] = useState(moment(new Date()).format('DD-MM-YYYY'));

    // Detect Mobile
    const [_, setWidth] = useState(window.innerWidth);

    function handleWindowSizeChange() {
        setWidth(window.innerWidth);
    }
    useEffect(() => {
        window.addEventListener('resize', handleWindowSizeChange);
        return () => {
            window.removeEventListener('resize', handleWindowSizeChange);
        }
    }, []);

    const plugins = [
        new ShortcutButtonsPlugin({
            button: [
                {
                    label: "Today"
                },
            ],
            //   label: "or",
            onClick: (index, fp) => {
                let date;
                switch (index) {
                    case 0:
                        date = new Date(today);
                        handleDateChange(date);
                        ReactTooltip.rebuild();
                        break;
                }
                fp.setDate(date);
                fp.close();
            }
        })
    ]

    const options = {
        enableTime: false,
        dateFormat: 'd-m-Y',
        altInput: true,
        clickOpens: false,
        formatDate: () => {
            if(selectedDate === todayChangedFormat)
            {
                return 'Today';
            }
            else
                return selectedDate;
        },
        minDate: firstRiskDate,
        maxDate: new Date(today),
        plugins,
        disableMobile: 'true'
    };

    function setIsLoading(action=false){
        // setLoading(action);
    }

    useEffect(() => {
        document.title = "Risk Dashboard";
    }, []);

    useDidMountEffect(async () => {
        dispatch({type: "reportGenerateLoader/show", payload: "Loading…"});
        let { payload } = await dispatch(
            fetchDashboardData({
                params: {
                    data_scope: appDataScope,
                    departments: selectedDepartments,
                    projects:selectedProjects,
                    filterDate:dateToFilter
                },
            })
        );
        dispatch({type: "reportGenerateLoader/hide"});
        if (!payload.success) return;
        let resData = payload.data;
        setRiskCountWithinRiskLevels(resData.riskCountWithinRiskLevels);
        renderCharts(resData);
        setLoaded(!loaded);
        ReactTooltip.rebuild();
    }, [selectedDepartments,appDataScope,selectedProjects,dateToFilter]);

    const renderCharts = (resData) => {
        // reset the charts
        setRiskSeverityChart(null);
        setRiskCloseStatusChart(null);

        //Donut piechart risk by severity
        let totalSeverityCount = resData.riskCountWithinRiskLevels.reduce(function(acc, val) { return acc + val.risk_count; }, 0);

        let rsc = {};
        rsc.labels = resData.riskCountWithinRiskLevels.map(function (item) {
            return item.name;
        });
        rsc.series = totalSeverityCount > 0 ? resData.riskCountWithinRiskLevels.map(function (item) {
            return item.risk_count;
        }) : [];
        rsc.options = {
            chart: {
                type: 'donut'
            },
            tooltip: {
                enabled: true,
                fillSeriesColor: false,
                theme:false,
                style:{
                    fontSize:'15px'
                }
            },
            colors: resData.riskLevelColors,
            labels: rsc.labels,
            legend: {
                show: totalSeverityCount > 0,
                position: 'right',
                formatter: function(seriesName, opts) {
                    return [seriesName, " - ", opts.w.globals.series[opts.seriesIndex]]
                }
            },
            dataLabels: {
                enabled: false,
                formatter: function (val, opts) {
                    return opts.w.config.series[opts.seriesIndex]
                }
            },
            states: {
                active: {
                    filter: {
                        type: 'none',
                    }
                }
            },
            plotOptions: {
                pie: {
                    expandOnClick: true,
                    donut: {
                        size: "90%",
                        background: "transparent",
                        labels: {
                            show: true,
                            name: {
                                show: false,
                                fontSize: "25px",
                                color: "black",
                            },
                            value:{
                                fontSize: "35px",
                                color: "#6e6b7b",
                            },
                            total: {
                                show: true,  
                                showAlways:true,
                                label:'Total Risks'
                            },
                        },
                    },
                },
            },
            noData:{
                text: "No data found",
                align: 'center',
                verticalAlign: 'middle',
            },
            responsive: [
                {
                    breakpoint: 480,
                    options: {
                        chart: {
                            // width: 310,
                        },
                        legend: {
                            position: 'bottom'
                        }
                    },
                },
            ],
        };

        setRiskSeverityChart(rsc);

        // Risk onb basic of risk closed status

        let totalClosedRiskCount = resData.closedRiskCountOfDifferentLevels.reduce(function(acc, val) { return acc + val; }, 0);

        let rcs = {};
        rcs.series = totalClosedRiskCount > 0 ? resData.closedRiskCountOfDifferentLevels : [];
        rcs.options = {
            chart: {
                type: 'donut'
            },
            tooltip: {
                enabled: true,
                fillSeriesColor: false,
                theme:false,
                style:{
                    fontSize:'15px'
                }
            },
            colors: resData.riskLevelColors,
            labels: resData.riskLevelsList,
            legend: {
                show: totalClosedRiskCount > 0,
                position: 'right',
                formatter: function(seriesName, opts) {
                    return [seriesName, " - ", opts.w.globals.series[opts.seriesIndex]]
                }
            },
            dataLabels: {
                enabled: false,
                formatter: function (val, opts) {
                    return opts.w.config.series[opts.seriesIndex]
                }
            },
            states: {
                active: {
                    filter: {
                        type: 'none',
                    }
                }
            },
            plotOptions: {
                pie: {
                    expandOnClick: true,
                    donut: {
                        size: "90%",
                        background: "transparent",
                        labels: {
                            show: true,
                            name: {
                                show: false,
                                fontSize: "25px",
                                color: "black",
                            },
                            value:{
                                fontSize: "35px",
                                color: "#6e6b7b",
                            },
                            total: {
                                show: true,  
                                showAlways:true,
                                label:'Total Closed'
                            }
                        },
                    },
                },
            },
            noData: {
                text: "No data found"
            },
            responsive: [
                {
                    breakpoint: 480,
                    options: {
                        chart: {
                            // width: 310,
                        },
                        legend: {
                            position: 'bottom'
                        }
                    },
                },
            ],
        };
        setRiskCloseStatusChart(rcs);

        // Apexchart stacked - risk by category
        let rbc = {};
        rbc.series = resData.riskCountWithinRiskLevelForCategories;
        rbc.options = {
            chart: {
                type: "bar",
                stacked: true,
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                },
            },
            stroke: {
                width: 1,
                colors: ["#fff"],
            },
            xaxis: {
                categories: resData.riskRegisterCategoriesList,
                labels: {
                    formatter: function (val) {
                        var num = Math.round(val + "e" + 3);
                        return Number(num + "e" + -3);
                    },
                },
            },
            yaxis: {
                title: {
                    text: undefined,
                },
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return val;
                    },
                },
            },
            fill: {
                opacity: 1,
            },
            legend: {
                position: "top",
                horizontalAlign: "left",
                offsetX: 40,
            },
            colors: resData.riskLevelColors,
            dataLabels: {
                style: {
                    colors: ["#38414a"],
                },
                formatter: function (val) {
                    return val !== 0 ? val : "";
                },
            },
        };
        // CODE TO ASSIGN HEIGHT STARTS HERE
        let categoriesLength = rbc.options.xaxis.categories.length;
        rbc.options.chart.height =
            categoriesLength > 0 ? calculateChartHeight(categoriesLength) : 0;
        setRiskByCategory(rbc);
    };

    const showRiskAddView = () => {
        window.scrollTo(0, 0);
    }

    const calculateChartHeight = (categoriesLength) => {
        // 33 increment for  100 start form 2nd bar
        let incrementRate = 32;
        let chartHeight = 100;

        if (categoriesLength > 1) {
            for (var i = 1; i < categoriesLength; i++) {
                chartHeight = chartHeight + incrementRate;
            }
        }

        return chartHeight;
    };

    const generateReport = async () => {
        const URL = route("risks.dashboard.generate-pdf-report");

        try {
            /* showing report generate loader */
            dispatch({ type: "reportGenerateLoader/show" });

            let response = await axiosFetch({
                url: URL,
                method: "Post",
                data: {
                    data_scope: appDataScope,
                    departments: selectedDepartments,
                    projects:selectedProjects,
                    filterDate:dateToFilter
                },
                responseType: "blob", // Important
            });

            fileDownload(response.data, `Risks Report ${moment().format('DD-MM-YYYY')}.pdf`);

            /* hiding report generate loader */
            dispatch({ type: "reportGenerateLoader/hide" });
        } catch (error) {
            /* hiding report generate loader */
            dispatch({ type: "reportGenerateLoader/hide" });
        }
    };

    const handleDateChange = async (value) => {
        flatPickrRef.current.flatpickr.close();
        var filterDate = moment(value).format('YYYY-MM-DD');
        var filterDateToShow = moment(value).format('DD-MM-YYYY');
        setSelectedDate(filterDateToShow);

        setDateToFilter(filterDate);
        ReactTooltip.rebuild();
    }

    return (
        <AppLayout>
            <div id="risk-dashboard-page">
                {/* <!-- breadcrumbs --> */}
                <div className="row">
                    <div className="col-12">
                            <div className="overview-div mt-4 d-flex justify-content-end">
                                <div className="input-group mb-1 filter-export" style={{width: 'auto'}}>
                                    <Flatpickr
                                        className={`form-control flatpickr-date clickable filter-date`}
                                        style={{
                                            width: "7.5rem",
                                        }}
                                        ref={flatPickrRef} 
                                        options={options}
                                        defaultValue={"today"}
                                        onChange={([val]) => {
                                            handleDateChange(val);
                                        }}
                                    />
                                    <input className="form-control flatpickr-date filter-date filter-date2 input active" onClick={() => { flatPickrRef.current.flatpickr.open(); }} placeholder="" tabIndex="0" type="text" readOnly></input>
                                    <div className="border-start-0">
                                        <span className="input-group-text cal-button bg-none" onClick={() => { flatPickrRef.current.flatpickr.open(); }}>
                                            <i className="mdi mdi-calendar-outline" />
                                        </span>
                                    </div>
                                </div>
                            </div>
                    </div>
                    </div>
                    <div className="row">
                    <div className="col-12">
                        <div className="d-flex flex-column align-items-md-center justify-content-between flex-md-row my-sm-1">
                            <h4 className="heading-medium">Dashboard</h4>
                            <div className="d-flex flex-column flex-md-row">
                                <DepartmentFilter
                                    ref={departmentFilterRef}
                                />

                                <ProjectFilter
                                    ref={projectFilterRef}
                                    className="mx-md-1 my-md-0 my-1"
                                />

                                { todayChangedFormat == selectedDate ?
                                    <button
                                        type="button"
                                        onClick={() => {
                                            generateReport();
                                        }}
                                        className="btn btn-primary risk-export_btn width-md"
                                    >
                                        Export to PDF
                                    </button>
                                    :
                                    <span data-tip='Change to current date to interact with the dashboard'
                                          className="btn btn-primary risk-export_btn width-md disabled_click"
                                    >
                                        Export to PDF
                                    </span>
                                }
                            </div>
                        </div>
                    </div>
                </div>
                {/* <!-- end of breadcrumbs --> */}

                {/* <!-- current vulnerability --> */}
                <div className="row">
                    <div className="col-xl-12">
                        <div className="risk-stat-div pb-1">
                            <h4 className="risk-stat-text">
                                Summary - Current Risks
                            </h4>
                        </div>
                    </div>
                </div>
                <div className="row">
                    {riskCountWithinRiskLevels.map(function (rcwrl, index) {
                        return (
                            <div className="col-md-6 col-xl-3" key={index}>
                                <div className="card">
                                    <div className="widget-rounded-circle card-body">
                                        <div className="row">
                                            <div className="col-6">
                                                <div
                                                    className="avatar-lg rounded-circle vulnerability__icon"
                                                    style={{
                                                        background: rcwrl.color,
                                                    }}
                                                >
                                                    <i
                                                        id="alert_icon"
                                                        className="icon fa fa-exclamation-triangle"
                                                    ></i>
                                                </div>
                                            </div>
                                            <div className="col-6">
                                                <div className="text-end">
                                                    <h3 className="text-dark mt-1">
                                                        {rcwrl.risk_count}
                                                    </h3>
                                                    <p className="text-muted mb-1 text-truncate">
                                                        {rcwrl.name}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* <!-- current vulnerability ends --> */}

                {/* <!-- pie charts --> */}
                <div className="row">
                    <div className="col-xl-12">
                        {/* <!-- pie charts --> */}
                        <div className="pie-charts">
                            <div className="row">
                                <div className="col-xl-6">
                                    <div className="card">
                                        <div className="donut-pie-chart card-body">
                                            <h4 className="header-title">
                                                Risks on the basis of severity
                                            </h4>
                                            {riskSeverityChart && (
                                                <Chart
                                                    options={
                                                        riskSeverityChart.options
                                                    }
                                                    labels={
                                                        riskSeverityChart.series
                                                    }
                                                    series={
                                                        riskSeverityChart.series
                                                    }
                                                    type="donut"
                                                    height={260}
                                                />
                                            )}
                                        </div>
                                    </div>
                                </div>

                                <div className="col-xl-6">
                                    <div className="card">
                                        <div className="radial-pie-chart card-body">
                                            <h4 className="header-title">
                                                Risks on the basis of closed
                                                status
                                            </h4>
                                            {riskCloseStatusChart && (
                                                <Chart
                                                    className="apexcharts"
                                                    options={
                                                        riskCloseStatusChart.options
                                                    }
                                                    series={
                                                        riskCloseStatusChart.series
                                                    }
                                                    type="donut"
                                                    height={260}
                                                />
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* <!-- risks-by-category starts --> */}
                <div className="row">
                    <div className="col-xl-12">
                        <div className="card">
                            <div className="risks-by-category mb-2 card-body">
                                <div className="risk-category-div">
                                    <h4 className="risk-category-text mt-0">
                                        Risks By Category
                                    </h4>
                                    {/* <div id="riskbycategory-chart" className="apexcharts"></div> */}
                                    {riskByCategory.series && (
                                        <Chart
                                            className="apexcharts"
                                            options={riskByCategory.options}
                                            series={riskByCategory.series}
                                            type="bar"
                                            height={
                                                riskByCategory.options.chart
                                                    .height
                                            }
                                        />
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="risk-register-page-dashboard">
                <div className="row">
                  <div className="project-box">
                    <div className="col-xl-12">
                    <div className="card">
                            <div className="risks-by-category mb-2 card-body">
                                <div className="risk-category-div">
                                    <h4 className="risk-category-text mt-0">
                                    Risk Register
                                    </h4>
                                    <RiskRegisterFilters
                                    searchTerm={searchTerm}
                                    onTermChange={handleTermChange}
                                    onlyIncomplete={filters.only_incomplete}
                                    onCheck={handleCheck}
                                    />
                                    <div id="risk-by-category-section">
                                    <RiskItemsSection showRiskAddView={showRiskAddView} primaryFilters={filters} categoryId={1} project_id={selectedProjects} setIsLoadingValue={(value) => setIsLoading(value)} handleUpdateRiskStatus={handleUpdateRiskStatus} dateToFilter={dateToFilter}/>
                                    <ReactTooltip />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                  </div>
                </div>
            </div>
            </div>
        </AppLayout>
    );
}

export default Dashboard;
