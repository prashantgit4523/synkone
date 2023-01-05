import React, { Fragment, useEffect, useState, useRef } from 'react';
import { Link, usePage } from '@inertiajs/inertia-react';

import { useSelector } from 'react-redux';
import DataTable from '../../common/custom-datatable/AppDataTable';
import fileDownload from 'js-file-download';

import './styles/global-task-monitor.scss';
import './styles/filter.scss';
import "flatpickr/dist/themes/light.css";

import AppLayout from '../../layouts/app-layout/AppLayout';
import ProjectFilter from './components/ProjectFilter';
import DepartmentFilter from './components/DepartmentFilter';
import BreadcumbsComponent from "../../common/breadcumb/Breadcumb";
import DepartmentFilterWithoutDataScope from './components/DepartmentFilterWithoutDataScope';
import ProjectFilterWithoutDataScope from './components/ProjectFilterWithoutDataScope';
import useDataTable from "../../custom-hooks/useDataTable";
function GlobalTaskMonitor() {
    const appDataScope = useSelector(state => state.appDataScope.selectedDataScope.value);
    const propsData = usePage().props;
    const fetchURL = propsData.taskListURL;
    const [refresh, setRefresh] = useState(false);
    const [allControls, setAllControls] = useState(propsData.all_controls);
    const [currentPage, setCurrentPage] = useState(propsData.currentPage);
    const [notApplicableControls, setNotApplicableControls] = useState(propsData.not_applicable);
    const urlSegmentTwo = propsData.urlSegmentTwo;
    const [selectedDepartments, setSelectedDepartments] = useState(propsData.selectedDepartments);
    const [selectedProjects, setSelectedProjects] = useState(propsData.selectedProjects);
    const authUser = propsData.authUser;
    const { projects } = useSelector(state => state.globalDashboardReducer.projectFilterReducer);
    const [isLoaded, setIsLoaded] = useState(false);

    const projectFilterRef = useRef(null)
    const departmentFilterRef = useRef(null)

    //Filters
    const [departments, setDepartments] = useState('');
    const [filterProjects, setFilterProjects] = useState('');
    const [filterDepartments, setFilterDepartments] = useState('');
    const [reloadData, setReloadData] = useState(false);
    const [reloadCounter, setReloadCounter] = useState(0);
    const [justRedirected, setJustRedirected] = useState(true);
    const [ajaxData, setAjaxData] = useState({
        all_controls:allControls,
        not_applicable:notApplicableControls,
        current_page:currentPage
    });
    const {q: keyword} = useDataTable('global-task-monitor');
    
    
    //Setting Form Values Based on Values From Query Params
    useEffect(() => {
        if (urlSegmentTwo == 'global') {
            //pre-select all the departments in department filter
            departmentFilterRef.current.handleRenderableDataUpdate(() => { })
        }
        if (selectedDepartments) {
            setFilterDepartments(selectedDepartments.map((c) => c.value).toString())
        }
        if (selectedProjects) {
            setFilterProjects(selectedProjects.map((c) => c.value).toString())
        }
        if (urlSegmentTwo == 'compliance') {
            //pre-select all the projects for filter
            // getAllProjects()
        }
    }, []);

    const getAllProjects = () => {
        try {
            axiosFetch.get(route('common.get-all-projects'))
                .then(res => {
                    setFilterProjects(res.data.projects.map((c) => c.value).toString());
                })
        } catch (error) {
            console.log("Response error");
        }
    }

    useEffect(() => {
        setReloadCounter(reloadCounter+1);
        if(reloadCounter>0){
            setJustRedirected(false);
        }
    }, [appDataScope])

    useEffect(() => {
        // runs when everything is mounted  
        if(isLoaded){
            projectFilterRef.current.toggleIsLoaded();
            setFilterProjects(projects.map((c) => c.id).toString());
        }
        if(reloadData){
            setIsLoaded(true);
        }
    }, [projects]);

    const toggleReloadData= () =>{
        setReloadData(true);
    }

    const handleProjectSelect = (data) => {
        if (urlSegmentTwo == 'compliance') {
            if (reloadCounter > 1) {
                setIsLoaded(true);
            }
            setReloadCounter(reloadCounter+1);
        }
        let latestFilterProjects = data.map((c) => c.value).toString()
        setFilterProjects(latestFilterProjects);
    }

    const handleSearch = () => {
        const data = {
            selected_departments: filterDepartments,
            selected_projects: filterProjects,
            all_controls:allControls,
            not_applicable:notApplicableControls,
            current_page:currentPage
        };
        if (urlSegmentTwo == 'compliance') {
            data.onlyUserData = true;
        }
        setAjaxData(data);
        setRefresh(!refresh);
    }

    useEffect(() => {
        if(isLoaded){
            handleSearch();
        }
    }, [filterDepartments, filterProjects])

    const handleExport = async () => {
        const data = {
            selected_departments: filterDepartments,
            selected_projects: filterProjects,
            all_controls:allControls,
            not_applicable:notApplicableControls,
            current_page:currentPage,
            keyword
        };
        if (urlSegmentTwo == 'compliance') {
            data.onlyUserData = true;
        }
        try {
            let response = await axiosFetch({
                url: route('compliance.tasks.export-data'),
                method: 'POST',
                data: data,
                responseType: 'blob', // Important
            })

            fileDownload(response.data, 'tasks.csv');
        } catch (error) {
            console.log(error);
        }
    }
    var url_string = window.location.href; //window.location.href
    var url = new URL(url_string);
    var departments_f = url.searchParams.get("selected_departments");
    var project_f = url.searchParams.get("selected_projects");
    let breadcrumb_url=urlSegmentTwo == 'compliance' ?route('compliance-dashboard'):route('global.dashboard')+'?selected_departments='+departments_f+'&selected_projects='+project_f;
    const breadcumbsData = {
        "title": `${urlSegmentTwo == 'compliance' ? 'My Task Monitor' : 'Global Task Monitor'}`,
        "breadcumbs": [
            {
                "title":`${urlSegmentTwo == 'compliance' ? 'Compliance' : 'Global'}`,
                "href": ""
            },
            {
                "title": "Dashboard",
                "href": breadcrumb_url
            },
            {
                "title": "Task Monitor",
                "href": ""
            },
        ]
    }

    const columns = [
        { accessor: 'project_name', label: 'Project', priority: 2, position: 1, minWidth: 100, sortable: true },
        { accessor: 'control', label: 'Control', priority: 3, position: 3, minWidth: 160, sortable: true },
        { accessor: 'control_description', label: 'Control Description', priority: 2, position: 4, minWidth: 170, sortable: true },
        { accessor: 'type', label: 'Type', priority: 1, position: 5, minWidth: 90, sortable: false, isHTML: true },
        { accessor: 'assigned', label: 'Assigned', priority: 2, position: 6, minWidth: 100, sortable: true, as: 'responsible',
            CustomComponent: ({ row }) => {
                return (
                    <Fragment>
                        {row.applicable ?
                         row.assigned:''   
                        }
                    </Fragment>
                )
            }
        },
        { accessor: 'approver', label: 'Approver', priority: 1, position: 7, minWidth: 100, sortable: true,
            CustomComponent: ({ row }) => {
                return (
                    <Fragment>
                        {row.applicable ?
                        row.approver:''   
                        }
                    </Fragment>
                )
            }
        },
        { accessor: 'completion_date', label: 'Completion Date', priority: 3, position: 8, minWidth: 160, sortable: true, as: 'approved_at',
            CustomComponent: ({ row }) => {
                return (
                    <Fragment>
                        {row.applicable ?
                        row.completion_date:''   
                        }
                    </Fragment>
                )
            }
        },
        { accessor: 'due_date', label: 'Due Date', priority: 3, position: 9, minWidth: 150, sortable: true, as: 'deadline',
            CustomComponent: ({ row }) => {
                return (
                    <Fragment>
                        {row.applicable ?
                        row.due_date:''   
                        }
                    </Fragment>
                )
            }
        },
        { accessor: 'task_status', label: 'Status', priority: 2, position: 10, minWidth: 100, sortable: false, isHTML: true },
        { accessor: 'approval_stage', label: 'Approval Stage', priority: 2, position: 11, minWidth: 140, sortable: true, isHTML: true, as: 'status'},
        {
            accessor: 'action', label: 'Action', priority: 4, position: 12, minWidth: 100, sortable: false,
            CustomComponent: ({ row }) => {
                return (
                    <Fragment>
                        {row.applicable && row.deleted_at === null ?
                        <Link
                            href={`${appBaseURL}/compliance/projects/${row.project_id}/controls/${row.id}/show/tasks`}
                            className="btn btn-primary go"
                            method="get">
                            Go
                        </Link>
                        :''
                        }
                    </Fragment>
                );
            },
        },
    ]

    return (
        <AppLayout>
            {/* <!-- page title here --> */}
            <BreadcumbsComponent data={breadcumbsData} />

            <div className="row global-task-monitor filter">
                <div className='col'>
                    <div className='card'>
                        <div className="card-body w-100">
                           <div className='row'>
                            <div className="col-12">
                                    <div className="filter-row d-flex flex-column flex-md-row justify-content-between my-2 p-2 rounded">
                                        <div className="filter-row__wrap d-flex flex-wrap">
                                            <div className="m-1 all-department">
                                                {urlSegmentTwo == 'compliance' ?
                                                    <DepartmentFilterWithoutDataScope ref={departmentFilterRef} /> :
                                                    <DepartmentFilter ref={departmentFilterRef} />
                                                }
                                            </div>
                                            <div className="all-projects m-1">
                                                {urlSegmentTwo == 'compliance' ?
                                                    <ProjectFilterWithoutDataScope ref={projectFilterRef} is_first={justRedirected} reloadFunction={toggleReloadData} actionFunction={handleProjectSelect} selectedProjects={selectedProjects} /> :
                                                    <ProjectFilter ref={projectFilterRef} is_first={justRedirected} reloadFunction={toggleReloadData} actionFunction={handleProjectSelect} selectedProjects={selectedProjects} />
                                                }
                                            </div>
                                        </div >
                                        {/* < !--/.filter-row__wrap--> */}
                                        <div className="m-1 text-center text-sm-auto w-10 task-button-wrapper" >
                                            {/* <form action="/global/tasks/export-data" method="GET"> */}
                                            <button id="export-data-btn" className="btn btn-primary" onClick={handleExport}> Export </button>
                                            {/* </form> */}
                                        </div>
                                    </div>
                                    {/* < !--/.filter-row--> */}
                                </div >
                                <div className="col-12">
                                    {/* <!-- table --> */}
                                    {(reloadData || urlSegmentTwo == 'compliance') && ajaxData && 
                                        <DataTable
                                            columns={columns}
                                            fetchUrl={fetchURL}
                                            data={ajaxData}
                                            refresh={refresh}
                                            tag="global-task-monitor"
                                            search
                                            emptyString="No data found"
                                        />
                                    }
                                </div>
                           </div>
                        </div>
                    </div>
                </div>
            </div >
        </AppLayout >
    );
}

export default GlobalTaskMonitor;
