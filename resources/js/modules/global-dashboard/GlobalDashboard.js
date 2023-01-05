import React, { Fragment, useEffect, useRef, useState } from "react";
import { useDispatch, useSelector } from "react-redux";
import CalendarWidget from "./components/calendar-widget/CalendarWidget";
import ControlStatusWidget from "./components/control-status-widget/ControlStatusWidget";
import TaskMonitorWidget from "./components/task-monitor-widget/TaskMonitorWidget";
import ImplementationProgress from "./components/ImplementationProgress";
import TaskCompletionPercentageWidget from "./components/task-completion-percentage-widget/TaskCompletionPercentageWidget";
import DepartmentFilter from "./components/department-filter/DepartmentFilter";
import ProjectFilter from "./components/project-filter/ProjectFilter";
import AppLayout from "../../layouts/app-layout/AppLayout";
import { fetchPageData } from "../../store/actions/global-dashboard";
import ContentLoader from "../../common/content-loader/ContentLoader";
import { useDidMountEffect } from "../../custom-hooks";
import { useStateIfMounted } from "use-state-if-mounted";
import fileDownload from "js-file-download";
import "./global-dashboard.scss";
import { Card, Row } from "react-bootstrap";
import { Col } from "react-bootstrap";
import Flatpickr from "react-flatpickr";
import "flatpickr/dist/themes/light.css";
import ShortcutButtonsPlugin from "shortcut-buttons-flatpickr";
import "shortcut-buttons-flatpickr/dist/themes/light.min.css";
import moment from 'moment/moment';
import {tableActions} from '../../store/dataTable/customDataTable';
import ReactTooltip from "react-tooltip";
import feather from "feather-icons";

function GlobalDashboard(props) {
    document.title = "Global Dashboard";

    const propsData = { props };


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
            if(index === 0){
                date = new Date(propsData.props.today);
                setClickable(false);
                handleDateChange(date);
                ReactTooltip.rebuild();
            }
            // fp.setDate(date);
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
            let selectedDate = moment(date).format('DD-MM-YYYY');
            if(selectedDate == moment(new Date(propsData.props.today)).format('DD-MM-YYYY'))
            {
                return 'Today';
            }
            else
                return selectedDate;
        },
        minDate: propsData.props.firstControlDate ? moment(new Date(propsData.props.firstControlDate)).format('YYYY-MM-DD 00:00:00') : moment(new Date(propsData.props.today)).format('YYYY-MM-DD 00:00:00'),
        maxDate: moment(new Date(propsData.props.today)).format('YYYY-MM-DD 23:59:59'),
        plugins,
        disableMobile: 'true'
    };

    const [clickable, setClickable] = useState(true);
    const [projectSelectedText, setProjectSelectedText] = useState('All projects');
    const [dateToFilter, setDateToFilter] = useState(moment(new Date(propsData.props.today)).format('YYYY-MM-DD'));
    const handleDateChange = (value) => {
        const filterDate = moment(value).format('YYYY-MM-DD');
        if(filterDate != moment(new Date(propsData.props.today)).format('YYYY-MM-DD'))
            setClickable(false);
        else
            setClickable(true);
        
        setDateToFilter(filterDate);
        flatPickrRef.current.flatpickr.setDate(filterDate);
        feather.replace();
    }
    const flatPickrRef = useRef(null);

    const globalSetting = propsData.props.globalSetting;
    const projectFilterRef = useRef(null);
    const departmentFilterRef = useRef(null);
    const [pageData, setPageData] = useStateIfMounted({});
    const appDataScope = useSelector(
        (state) => state.appDataScope.selectedDataScope.value
    );
    const { selectedProjects } = useSelector(
        (store) => store.globalDashboardReducer.projectFilterReducer
    );

    const { projects } = useSelector(state => state.globalDashboardReducer.projectFilterReducer)

    const selectedDepartments = useSelector(
        (store) =>
            store.commonReducer.departmentFilterReducer.selectedDepartment
    );
    const [showContentLoader, setShowContentLoader] = useStateIfMounted(false);
    const dispatch = useDispatch();
    //when visiting this url,the task monitor is reset
    useEffect(()=>{
        dispatch(tableActions.customDataTableSetInitialState({
            tableTag: 'global_task_monitor'
        }))
    },[])

    /* On selectedProjects update */
    useDidMountEffect(async () => {
        if(selectedProjects.length==1){
            let projectSelected=projects.filter( x => x.id === selectedProjects[0]);
            setProjectSelectedText(projectSelected[0].name);
        }
        else if( selectedProjects.length > 1 && selectedProjects.length != projects.length ){
            setProjectSelectedText('Multiple Projects');
        }
        else if(selectedProjects.length > 0 ){
            setProjectSelectedText('All projects');
        }
        else{
            setProjectSelectedText('None');
        }
        setShowContentLoader(true);
        let res = await dispatch(
            fetchPageData({
                data_scope: appDataScope,
                projects: selectedProjects.join(),
                date: dateToFilter
            })
        );

        /* Setting the page data */
        if (res.payload && res.payload.success) {
            setPageData(res.payload.data);
        }

        setShowContentLoader(false);
        ReactTooltip.rebuild();
    }, [selectedProjects]);

    useDidMountEffect(() => {
        /* Un-selecting prev-selected before re-render */
        // unSelectAll()

        let data = projects.map((project) => {
            return {
                value: project.id,
                label: project.name
            }
        })
    }, [projects])
    /* Generate the PDF report */
    const generateReport = async () => {
        let URL = route("global.dashboard.generate-report");

        /* showing report generate loader */
        dispatch({ type: "reportGenerateLoader/show" });

        try {
            let response = await axiosFetch({
                url: URL,
                method: "Post",
                data: {
                    data_scope: appDataScope,
                    projects: selectedProjects.join(),
                    date: dateToFilter,
                },
                responseType: "blob", // Important
            });

            fileDownload(response.data, `Global Report ${moment().format('DD-MM-YYYY')}.pdf`);

            /* hiding report generate loader */
            dispatch({ type: "reportGenerateLoader/hide" });
        } catch (error) {
            /* hiding report generate loader */
            dispatch({ type: "reportGenerateLoader/hide" });
        }
    };

    /* Selects all projects */
    const selectAllProjects = () => {
        projectFilterRef.current.selectAll();
    };

    return (
        <AppLayout>
            <div id="global-dashboard">
                {/* top section */}
                <Row>
                    <Col xl={12}>
                        <div className="overview-div mt-3 d-flex justify-content-end">
                            <div className="input-group mt-1 date-filter">
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
                        </div>
                    </Col>
                    <Col xl={12}>
                        <div className="d-flex flex-column align-items-md-center justify-content-between flex-md-row my-2">
                            <div className="overview-div-text">
                                <h4 className="overview-text">
                                    Current Overview:&nbsp;
                                    <span className="overview-break-text">
                                        { projectSelectedText }
                                    </span>
                                </h4>
                            </div>

                            <div className="d-flex flex-column flex-md-row">
                                <DepartmentFilter
                                    ref={departmentFilterRef}
                                    filterDate={dateToFilter}
                                />

                                <ProjectFilter
                                    ref={projectFilterRef}
                                    filterDate={dateToFilter}
                                    className="mx-md-1 my-md-0 my-1"
                                />

                                { clickable ? (
                                        <button
                                            onClick={() => {
                                                generateReport();
                                            }}
                                            className="btn btn-primary global-export_btn dashboard-btn"
                                        >
                                            Export to PDF
                                        </button>
                                    ) : (
                                        <span
                                            data-tip='Change to current date to interact with the dashboard'
                                            className="btn btn-primary global-export_btn dashboard-btn disabled_click"
                                        >
                                            Export to PDF
                                        </span>
                                )}
                            </div>
                        </div>
                    </Col>
                </Row>

                <ContentLoader show={showContentLoader}>
                    <div className="task-box loader-overlay">
                        <ControlStatusWidget
                            allControls={pageData.allControls}
                            notApplicableControls={
                                pageData.notApplicableControls
                            }
                            implementedControls={
                                pageData.implementedControls
                            }
                            underReviewControls={
                                pageData.underReviewControls
                            }
                            notImplementedControls={
                                pageData.notImplementedControls
                            }
                            clickableStatus={clickable}
                        ></ControlStatusWidget>
                    </div>
                    <div className="task-box loader-overlay my-3">
                        <Row>
                            <Col xl={8} className="mb-2 mb-md-0">
                                <Card className="h-100">
                                    <Card.Body>
                                        <h4 className="head-title mt-0">Task Monitor</h4>
                                        <Row>
                                            <Col md={6}>
                                            <TaskCompletionPercentageWidget
                                                completedTasksPercent={
                                                    pageData.completedTasksPercent
                                                }
                                                globalSetting={globalSetting}
                                            ></TaskCompletionPercentageWidget>
                                            </Col>
                                            <Col md={6}>
                                                <TaskMonitorWidget
                                                    allUpcomingTasks={pageData.allUpcomingTasks}
                                                    allDueTodayTasks={pageData.allDueTodayTasks}
                                                    allPassDueTasks={pageData.allPassDueTasks}
                                                    clickableStatus={clickable}
                                                ></TaskMonitorWidget>
                                            </Col>
                                        </Row>
                                    </Card.Body>
                                </Card>
                            </Col>
                            <Col xl={4}>
                                <ImplementationProgress
                                    allControls={pageData.allControls}
                                    notApplicableControls={
                                        pageData.notApplicableControls
                                    }
                                    implementedControls={
                                        pageData.implementedControls
                                    }
                                    underReviewControls={
                                        pageData.underReviewControls
                                    }
                                    notImplementedControls={
                                        pageData.notImplementedControls
                                    }
                                ></ImplementationProgress>
                            </Col>
                        </Row>
                    </div>
                    {/* calendar here */}
                    <CalendarWidget dateToFilter={dateToFilter} handleDateChange={handleDateChange} clickableStatus={clickable}></CalendarWidget>
                    {/* calendar here ends*/}
                </ContentLoader>
            </div>
        </AppLayout>
    );
}

export default GlobalDashboard;
