import React, {useEffect, useState, useRef} from "react";

import {useSelector, useDispatch} from "react-redux";
import {Link} from "@inertiajs/inertia-react";
import fileDownload from "js-file-download";

import AppLayout from "../../../layouts/app-layout/AppLayout";
import DataTable from "../../../common/custom-datatable/AppDataTable";
import Chart from "react-apexcharts";
import Flatpickr from "react-flatpickr";
import "flatpickr/dist/themes/light.css";
import ShortcutButtonsPlugin from "shortcut-buttons-flatpickr";
import "shortcut-buttons-flatpickr/dist/themes/light.min.css";
import moment from 'moment/moment';
import ReactTooltip from "react-tooltip";

import "../style/style.scss";

const defaultLevels = [
    {
        color: "#ff0000",
        count: 0,
        name: "Level 1",
    },
    {
        color: "#ffc000",
        count: 0,
        name: "Level 2",
    },
    {
        color: "#ffff00",
        count: 0,
        name: "Level 3",
    },
    {
        color: "#92d050",
        count: 0,
        name: "Level 4",
    },
    {
        color: "#00b050",
        count: 0,
        name: "Level 5",
    },
];
const defaultProgress = {
    "Overdue": 0,
    "Completed": 0,
    "In Progress": 0,
    "Not Started": 0
}

const Index = (props) => {
    const flatPickrRef = useRef(null);
    const { firstProjectDate, today } = props;
    const [vendorLevels, setVendorLevels] = useState(defaultLevels);
    const [clickable, setClickable] = useState(true);
    const [dateToFilter, setDateToFilter] = useState(moment(new Date()).format('YYYY-MM-DD'));
    const [projectsProgress, setProjectsProgress] = useState(defaultProgress);
    const [refreshToggle, setRefreshToggle] = useState(false);


    // Detect Mobile
    const [width, setWidth] = useState(window.innerWidth);

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
                        setClickable(false);
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
        dateFormat: "Y-m-d",
        altFormat: 'd-m-Y',
        altInput: true,
        formatDate: (date) => {
            setRefreshToggle(false);
            let selectedDate = moment(date).format('DD-MM-YYYY');
            if(selectedDate == moment(new Date()).format('DD-MM-YYYY'))
            {
                return 'Today';
            }
            else
                return selectedDate;
        },
        minDate: firstProjectDate ? moment(new Date(firstProjectDate)).format('YYYY-MM-DD 00:00:00') : moment(new Date(today)).format('YYYY-MM-DD 00:00:00'),
        maxDate: moment(new Date(today)).format('YYYY-MM-DD 23:59:59'),
        plugins,
        disableMobile: 'true'
    };

    const dispatch = useDispatch();
    const appDataScope = useSelector(state => state.appDataScope.selectedDataScope.value);

    const handleDateChange = (value) => {
        let filterDate = moment(value).format('YYYY-MM-DD');
        if(filterDate != moment(new Date()).format('YYYY-MM-DD'))
            setClickable(false);
        else
            setClickable(true);

        setDateToFilter(filterDate);
        setRefreshToggle(true);
        axiosFetch.get(route('third-party-risk.dashboard.get-vendors-data'), {
            params: {
                data_scope: appDataScope,
                date_to_filter: filterDate
            }
        })
        .then(({data: {levels, projects_progress}}) => {
            setVendorLevels(levels);
            setProjectsProgress(projects_progress);
        });
        ReactTooltip.rebuild();
    }

    useEffect(() => {
        setRefreshToggle(!refreshToggle);
        axiosFetch.get(route('third-party-risk.dashboard.get-vendors-data'), {
            params: {
                data_scope: appDataScope,
            }
        })
            .then(({data: {levels, projects_progress}}) => {
                setVendorLevels(levels);
                setProjectsProgress(projects_progress);
            })

            console.log(projectsProgress,'console');
    }, [appDataScope]);

    useEffect(() => {
        document.title = "Third Party Risk Dashboard";
    }, []);


    const progressChartOptions = {
        chart: {
            zoom: {
                enabled: true,
                type: "x",
                autoScaleYaxis: false,
                zoomedArea: {
                    fill: {
                        color: "#90CAF9",
                        opacity: 0.4,
                    },
                    stroke: {
                        color: "#0D47A1",
                        opacity: 0.4,
                        width: 1,
                    },
                },
            },
        },
        dataLabels: {
            enabled: false,
        },
        legend: {
            show: true,
            position: 'right',
            offsetX:50,
            formatter: function(seriesName, opts) {
                return [seriesName, " - ", opts.w.globals.series[opts.seriesIndex]]
            }
        },
        responsive: [
            {
              breakpoint: 600,
              options: {
                plotOptions: {
                  bar: {
                    horizontal: false
                  }
                },
                legend: {
                  position: "bottom",
                  offsetX:0
                }
              }
            }
          ],
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
                            label:'Total'
                        },
                    },
                },
            },
        },
        tooltip: {
            enabled: true,
            fillSeriesColor: false,
            theme:false,
            style:{
                fontSize:'15px'
            }
        },
        labels: Object.keys(projectsProgress),
        colors: ["#414141", "#5bc0de", "#359f1d", "#cf1110"],
    }

    const vendorLevelChartOptions = {
        chart: {
            zoom: {
                enabled: true,
                type: "x",
                autoScaleYaxis: false,
                zoomedArea: {
                    fill: {
                        color: "#90CAF9",
                        opacity: 0.4,
                    },
                    stroke: {
                        color: "#0D47A1",
                        opacity: 0.4,
                        width: 1,
                    },
                },
            },
        },
        colors: vendorLevels.map(v => v.color),
        labels: vendorLevels.map(v => v.name),
        dataLabels: {
            enabled: false,
        },
        legend: {
            show: true,
            position: 'right',
            offsetX:50,
            formatter: function(seriesName, opts) {
                return [seriesName, " - ", opts.w.globals.series[opts.seriesIndex]]
            }
        },
        responsive: [
            {
              breakpoint: 600,
              options: {
                plotOptions: {
                  bar: {
                    horizontal: false
                  }
                },
                legend: {
                  position: "bottom",
                  offsetX:0
                }
              }
            }
          ],
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
                            label:'Total'
                        },
                    },
                },
            },
        },
        tooltip: {
            enabled: true,
            fillSeriesColor: false,
            theme:false,
            style:{
                fontSize:'15px'
            }
        },
    };

    const columns = [
        {
            accessor: "name",
            label: "Vendor Name",
            priority: 2,
            position: 1,
            minWidth: 120,
            sortable: true
        },
        {
            accessor: "score",
            label: "Score",
            priority: 2,
            position: 2,
            minWidth: 90,
            sortable: true
        },
        {
            accessor: "maturity",
            label: "Maturity",
            priority: 2,
            position: 3,
            minWidth: 120,
            CustomComponent: ({row}) => {
                return (
                    <>
                        <span
                            className="badge text-white"
                            style={{
                                textOverflow: "ellipsis",
                                overflow: "hidden",
                                backgroundColor:
                                    row.level === 1
                                        ? "#ff0000"
                                        : row.level === 2
                                            ? "#ffc000"
                                            : row.level === 3
                                                ? "#ffff00"
                                                : row.level === 4
                                                    ? "#92d050"
                                                    : "#00b050",
                            }}
                        >
                            {`Level ${row.level}`}
                        </span>
                    </>
                );
            },
            sortable: true,
            as: 'score'
        },
        {
            accessor: "status",
            label: "Status",
            priority: 1,
            position: 4,
            minWidth: 120,
            CustomComponent: ({row}) => {
                return (
                    <span
                        className={`badge ${row.vendor_with_trashed?.status === 'active' ? 'bg-success' : 'bg-dark'}`}
                        style={{
                            textOverflow: "ellipsis",
                            overflow: "hidden",
                        }}
                    >
                                {row.vendor_with_trashed?.status === 'active' ? 'Active' : 'Disabled'}
                            </span>
                );
            },
            sortable: true
        },
        {
            accessor: "contact_name",
            label: "Contact Name",
            priority: 1,
            position: 5,
            minWidth: 130,
            sortable: true
        },
        {
            accessor: 'actions',
            label: 'Actions',
            priority: 3,
            minWidth: 120,
            position: 6,
            CustomComponent: ({row}) => {
                if(row.latest_project){
                return(
                    clickable
                    ? <Link href={route('third-party-risk.projects.show', [row.latest_project?.id])} className="btn btn-primary btn-view btn-sm width-sm clickable">View</Link>
                    : <Link href="#" className="btn btn-primary btn-view btn-sm width-sm nonClickable" data-tip="Change to current date to interact with the dashboard" onClick={ (event) => event.preventDefault() }>View</Link>
                )
                }else{
                    return <></>;
                }
            }
        }
    ];

    const handleExportPDF = () => {
        dispatch({type: "reportGenerateLoader/show"});
        axiosFetch.get(route('third-party-risk.dashboard.export-pdf'), {
                params: {
                    data_scope: appDataScope,
                    date_to_filter: dateToFilter
                },
                responseType: 'blob',
            }
        ).then(res => {
            fileDownload(res.data, `Third Party Risk Report ${moment().format('DD-MM-YYYY')}.pdf`);
        }).finally(() => {
            dispatch({type: "reportGenerateLoader/hide"});
        })
    }

    return (
        <AppLayout>
            <div id="third-party-risk-page">
                <div className="row">
                    <div className="col-12">
                        <div className="d-flex flex-column align-items-md-center justify-content-between flex-md-row mt-3 mb-2">
                            <h4 className="heading-medium">My Dashboard</h4>
                            <div className="d-flex flex-column flex-md-row">
                                <div className="input-group me-md-1 my-md-0 my-1 filter-export">
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
                                    <div className="border-start-0">
                                        <span className="input-group-text cal-button bg-none" onClick={() => { flatPickrRef.current.flatpickr.open(); }}>
                                            <i className="mdi mdi-calendar-outline" />
                                        </span>
                                    </div>
                                </div>
                                { clickable ? (
                                    <button
                                    type="button"
                                    onClick={handleExportPDF}
                                    className="btn btn-primary risk-export_btn width-md"
                                    >
                                        Export to PDF
                                    </button>
                                    ) : (
                                        <span
                                            data-tip='Change to current date to interact with the dashboard'
                                            className="btn btn-primary dashboard-btn disabled_click"
                                        >
                                            Export to PDF
                                        </span>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
                <div className="row">
                    <div className="col-xl-12">
                        <div className="risk-stat-div pb-1">
                            <h4 className="risk-stat-text">
                                Summary - Vendor Maturity
                            </h4>
                        </div>
                    </div>
                </div>

                <div className="row row-cols-1 row-cols-md-2 row-cols-lg-5 g-3">
                    {vendorLevels.map(function (level, index) {
                        return (
                            <div className="col" key={index}>
                                <div className="card mb-0">
                                    <div className="card-body">
                                        <div className="widget-rounded-circle">
                                            <div className="row">
                                                <div className="col-6">
                                                    <div
                                                        className="avatar-lg rounded-circle vulnerability__icon"
                                                        style={{
                                                            background: level.color,
                                                        }}
                                                    >
                                                        <i
                                                            id="alert_icon"
                                                            className="icon fa fa-user-shield"
                                                        />
                                                    </div>
                                                </div>
                                                <div className="col-6">
                                                    <div className="text-end">
                                                        <h3 className="text-dark mt-1">
                                                            {level.count}
                                                        </h3>
                                                        <p className="text-muted mb-1 text-truncate">
                                                            {level.name}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
                <div className="row">
                    <div className="col-xl-12">
                        {/* <!-- pie charts --> */}
                        <div className="pie-charts">
                            <div className="row">
                                <div className="col-xl-6">
                                    <div className="card mt-3 mb-0 mb-xl-3">
                                        <div className="card-body">
                                            <div className="donut-pie-chart">
                                                <h4 className="header-title">
                                                    Vendors on the basis of maturity
                                                </h4>
                                                {vendorLevels.reduce(function(acc, val) { return acc + val.count; }, 0) > 0 ? <Chart
                                                    options={vendorLevelChartOptions}
                                                    series={vendorLevels.map(l => l.count)}
                                                    type="donut"
                                                    height={260}
                                                />: <div style={{textAlign:'center',padding:'140px 0px 0px 0px'}}>No data found</div>}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div className="col-xl-6">
                                   <div className="card mt-3">
                                        <div className="card-body">
                                            <div className="radial-pie-chart">
                                                <h4 className="header-title">
                                                    Vendor risk questionnaire progress
                                                </h4>
                                                {Object.values(projectsProgress).reduce(function(acc, val) { return acc + val; }, 0) > 0 ? 
                                                <Chart
                                                    className="apexcharts"
                                                    options={progressChartOptions}
                                                    series={Object.values(projectsProgress)}
                                                    type="donut"
                                                    height={260}
                                                />:  <div style={{textAlign:'center',padding:'140px 0px 0px 0px'}}>No data found</div>}
                                            </div>
                                        </div>  
                                   </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="row">
                    <div className="col-xl-12">
                        <div className="card">
                            <div className="card-body">
                                <div className="top-risk pb-1">
                                    <h4 className="top-risk-text mt-0">
                                        Top Vendors
                                    </h4>
                                </div>
                                    <DataTable
                                        data-tip="Change to current date to interact with the dashboard"
                                        fetchUrl={route('third-party-risk.dashboard.get-top-vendors')}
                                        columns={columns}
                                        refresh={refreshToggle}
                                        dateToFilter={dateToFilter}
                                        tag={'third-party-risk-dashboard'}
                                        search
                                        emptyString="No data found"
                                    />
                                    <ReactTooltip />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

export default Index;
