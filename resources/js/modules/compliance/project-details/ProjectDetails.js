import React, {useEffect, useState, useRef} from 'react';

import {Inertia} from '@inertiajs/inertia';
import {Link} from '@inertiajs/inertia-react';
import {useDispatch, useSelector} from 'react-redux';
import {Tabs, Tab} from 'react-bootstrap';

import AppLayout from '../../../layouts/app-layout/AppLayout';
import Breadcrumb from '../../../common/breadcumb/Breadcumb';
import FlashMessages from '../../../common/FlashMessages';
import ContentLoader from '../../../common/content-loader/ContentLoader';
import useDataTable from "../../../custom-hooks/useDataTable";
import DataTable from '../../../common/custom-datatable/AppDataTable';
import BulkAssignmentModal from '../components/BulkAssignmentModal';
import { storeGroupSelectData } from '../../../store/actions/native-awareness/groupSelectData';

import Chart from 'react-apexcharts';
import Select from '../../../common/custom-react-select/CustomReactSelect';
import Flatpickr from 'react-flatpickr';
import fileDownload from 'js-file-download';
import feather from 'feather-icons';
import moment from 'moment/moment';

import 'flatpickr/dist/themes/light.css';
import './style.scss';

const SaveButton = ({disabled, onClick, loading}) => {
    return (
        <div className="save-button d-flex justify-content-end my-2">
            <button
                className={`btn btn-primary custom-save-button ${loading ? 'expandRight' : ''}`}
                onClick={onClick}
                disabled={disabled || loading}
            >
                Save
                <span className="custom-save-spinner">
                    <img
                        className="custom-spinner-image"
                        alt="loading spinner"
                        height="25px"
                        style={{display: loading ? 'block' : 'none'}}
                    />
                </span>
            </button>
        </div>
    );
}

const frequencySelectOptions = [
    {value: 'One-Time', label: 'One-Time'},
    {value: 'Monthly', label: 'Monthly'},
    {value: 'Every 3 Months', label: 'Every 3 Months'},
    {value: 'Bi-Annually', label: 'Bi-Annually'},
    {value: 'Annually', label: 'Annually'}
];

const breadcrumbs = {
    "title": "Project Details",
    "breadcumbs": [
        {
            "title": "Compliance",
            "href": route('compliance-dashboard')
        },
        {
            "title": "Projects",
            "href": route('compliance-projects-view')
        },
        {
            "title": "Details",
            "href": "#"
        },
    ]
}

function ProjectDetails(props) {
    const tag = `project-${props.project.id}-controls`;
    const controlDisabled = props.control_disabled;

    const [controlsAdmins, setControlsAdmins] = useState([]);
    const [contributors, setContributors] = useState([]);
    const [errors, setErrors] = useState([]);
    const [isSaving, setIsSaving] = useState(false);
    const [pieData, setPieData] = useState();
    const [projectStat, setProjectStat] = useState();
    const [refreshDatatable, setRefreshDatatable] = useState(false);

    const appDataScope = useSelector(state => state.appDataScope.selectedDataScope.value);
    const {updateRow, data: fetchedControls} = useDataTable(tag);

    const dataScopeRef = useRef(appDataScope);
    const dispatch = useDispatch();

    const bulkProcessing = useRef(false);
    const dataChanged = useRef(false);

    useEffect(() => {
        document.title = "Project Details";
        if (dataScopeRef.current !== appDataScope) {
            Inertia.get(route("compliance-projects-view"));
        }
        feather.replace();
        getAndSetPieData();
        dispatch(storeGroupSelectData('All SSO users'));
    }, [appDataScope]);

    useEffect(() => {
        axiosFetch.get(route('common.get-users-by-department'), {
            params: {
                data_scope: appDataScope
            }
        }).then((res) => setControlsAdmins(res.data));

        axiosFetch.get(route('common.contributors'),{params:{editable:0}})
        .then(res => {
            let all_contributors=Object.keys(res.data).map((c) => ({
                value: res.data[c],
                label: c,
            }));
            setContributors(all_contributors);
        });
    }, [appDataScope]);

    const handleFieldChange = (fieldName) => (e, row) => {
        const copy = {...row};

        if (fieldName === 'responsible') {
            copy.responsible = e?.value ?? null;
        } else if (fieldName === 'approver') {
            copy.approver = e?.value ?? null;
        } else if (fieldName === 'deadline') {
            const offset = e.getTimezoneOffset();
            e = new Date(e.getTime() - (offset * 60 * 1000));
            copy.deadline = e.toISOString().split('T')[0];
        } else if (fieldName === 'frequency') {
            copy.frequency = e.value;
        } else if (fieldName === 'applicable') {
            copy.applicable = e.target.checked;
        }

        if(!copy.responsible && copy.approver || copy.responsible && !copy.approver && copy.automation === 'none'){
            if(!errors.includes(row['id'])) {
                setErrors((errors) => [...errors, row['id']]);
            }
        } else {
            setErrors((errors) => errors.filter(e => e !== row['id']));
        }

        updateRow(copy['id'], copy, () => dataChanged.current = true);
    }

    const saveAndProceed = () => {
        setIsSaving(true);

        axiosFetch.post(
            route('compliance-project-controls-update-all-json', props.project.id),
            {controls: fetchedControls}
        )
            .then(function () {
                getAndSetPieData();
                if (document.getElementsByClassName('page-link').length) {
                    const last_page_link_element = document.getElementsByClassName('page-link').length - 2;
                    const is_last_page = document.getElementsByClassName('page-link')[last_page_link_element].classList.contains('active');
                    if (is_last_page) {
                        AlertBox({
                            title: "Control assignment updated successfully!",
                            showCancelButton: false,
                            confirmButtonColor: '#b2dd4c',
                            confirmButtonText: 'OK',
                            icon: 'success',
                        })
                    } else {
                        AlertBox({
                            title: "Control assignment updated successfully!",
                            text: "Do you want to continue to the next page?",
                            showCancelButton: true,
                            confirmButtonColor: '#b2dd4c',
                            confirmButtonText: 'Yes',
                            cancelButtonText: "No",
                            icon: 'success',
                        }, function (confirmed) {
                            if (confirmed.value && confirmed.value === true) {
                                const next_page_link_element = document.getElementsByClassName('page-link').length - 1;
                                document.getElementsByClassName('page-link')[next_page_link_element].click()
                            } else {
                                setRefreshDatatable(!refreshDatatable);
                            }
                        })
                    }
                } else {
                    AlertBox({
                        title: "Control assignment updated successfully!",
                        showCancelButton: false,
                        confirmButtonColor: '#b2dd4c',
                        confirmButtonText: 'OK',
                        icon: 'success',
                    })
                }
            })
            .finally(() => {
                setIsSaving(false);
                dataChanged.current = false;
                setErrors([]);
            });
    }

    useEffect(() => {
        const removeEventListener = Inertia.on('before', (e) => {
            if(dataChanged.current && !bulkProcessing.current) {
                e.preventDefault();
                AlertBox({
                    title: 'Are you sure?',
                    text: 'You didn\'t save your changes.',
                    confirmButtonColor: '#6c757d',
                    cancelButtonColor: '#f1556c',
                    allowOutsideClick: false,
                    icon: 'warning',
                    iconColor: '#f1556c',
                    showCancelButton: true,
                    confirmButtonText: 'Cancel',
                    cancelButtonText: 'Leave'
                }, function (result) {
                    if (!result.isConfirmed) {
                        removeEventListener();
                        Inertia.get(e.detail.visit.url.href);
                    }
                })
            }
        });

        return removeEventListener;
    }, [bulkProcessing.current, dataChanged.current])

    const getSelectValue = (row, key, from = controlsAdmins) => {
        if (row[key]) {
            return from.find(e => e.value === row[key]);
        }
        return null;
    }

    const getSelectOptions = (row, key) => controlsAdmins.map((a) => {
        if (a.value === row[key]) {
            return {...a, isDisabled: true}
        }
        return a;
    });

    const columns = [
        {
            accessor: 'applicable', label: 'Applicable', priority: 1, position: 2, minWidth: 80, sortable: true,
            CustomComponent: ({row}) => {
                return (
                    <div className="checkbox checkbox-success cursor-pointer">
                        <input
                            id={"applicable-checkbox" + row.id}
                            type="checkbox"
                            disabled={!row.is_editable || controlDisabled}
                            checked={row.applicable}
                            onChange={(e) => handleFieldChange('applicable')(e, row)}/>
                        <label htmlFor={"applicable-checkbox" + row.id}></label>
                    </div>
                );
            },
        },
        {
            accessor: 'controlId',
            label: 'Control ID',
            priority: 1,
            position: 3,
            minWidth: 100,
            sortable: true,
            as: 'full_control_id'
        },
        {
            accessor: 'name', label: 'Name', priority: 2, position: 4, minWidth: 120, sortable: true,
            CustomComponent: ({row}) => {
                if (!row.applicable) return <span className="control-name-column">{row.name}</span>
                return (
                    <Link
                        id={`control_link_name_${row.id}`}
                        href={route('compliance-project-control-show', [row.project_id, row.id, 'tasks'])}
                    >
                        {row.name}
                    </Link>
                );
            },
        },

        {
            accessor: 'description',
            label: 'Description',
            priority: 3,
            position: 5,
            minWidth: 180,
            sortable: true,
            CustomComponent: ({row}) => <span>{row.description}</span>
        },
        {
            accessor: 'status', label: 'Status', priority: 3, position: 6, minWidth: 130, sortable: true,
            CustomComponent: ({row}) => {
                let class_name;
                let row_status = row.status;

                if (!row.applicable) {
                    class_name = 'badge task-status-purple w-60';
                    row_status = 'Not Applicable';
                } else if (row.status === 'Not Implemented') {
                    class_name = 'badge task-status-red w-60';
                } else if (row.status === 'Implemented') {
                    class_name = 'badge task-status-green w-60';
                } else if (row.status === 'Rejected') {
                    class_name = 'badge task-status-orange w-60';
                } else {
                    class_name = 'badge task-status-blue w-60';
                }

                return (
                    <span id={`task-status${row.id}`} className={class_name}>{row_status}</span>
                );
            },
        },
        {
            accessor: 'automation', label: 'Automation', priority: 2, position: 7, minWidth: 100, sortable: true,
            CustomComponent: ({row}) => {
                let class_name = '';

                if (row.automation === 'technical') {
                    class_name = 'badge task-status-purple w-60';
                } else if (row.automation === 'document') {
                    class_name = 'badge task-status-purple w-60';
                } else if (row.automation === 'awareness') {
                    class_name = 'badge task-status-purple w-60';
                } else if (row.automation === 'none') {
                    class_name = 'badge task-status-red w-60';
                }

                return (
                    <span
                        id={"task-automation" + row.id}
                        style={{textTransform: 'capitalize'}}
                        className={class_name}
                    >
                        {row.automation}
                    </span>
                );
            },
        },
        {
            accessor: 'responsible',
            label: 'Responsible',
            canOverflow: true,
            priority: 4,
            position: 8,
            minWidth: 160,
            sortable: true,
            CustomComponent: ({row}) => {
                const error = row.automation === 'none' && !row.responsible && row.approver;
                const implemented_sgd_control = (row.isSgdControl === true || row.automation === 'awareness')  && row.status == 'Implemented';
                return (
                    <div className="position-relative">
                        {error && <p className="tootip bg-danger row-input-error">You must select a responsible!</p>}
                        <Select
                            className="react-select"
                            classNamePrefix="react-select"
                            onChange={(v) => handleFieldChange('responsible')(v, row)}
                            value={implemented_sgd_control?getSelectValue(row, 'responsible',contributors):getSelectValue(row, 'responsible')}
                            options={implemented_sgd_control?contributors:getSelectOptions(row, 'approver')}
                            menuPortalTarget={document.querySelector('body')}
                            isDisabled={!row.is_editable || controlDisabled || !row.applicable}
                            isClearable
                            isSearchable
                            name="responsible"
                        />
                    </div>
                );
            },
        },
        {
            accessor: 'approver', label: 'Approver', priority: 4, position: 9, minWidth: 160, sortable: true, canOverflow: true,
            CustomComponent: ({row}) => {
                const error = row.automation === 'none' && row.responsible && !row.approver;
                return (
                    <div className="position-relative">
                        {error && <p className="tootip bg-danger row-input-error">You must select an approver!</p>}
                        <Select
                            className="react-select"
                            classNamePrefix="react-select"
                            onChange={(v) => handleFieldChange('approver')(v, row)}
                            value={getSelectValue(row, 'approver')}
                            options={getSelectOptions(row, 'responsible')}
                            isClearable
                            isSearchable
                            isDisabled={row.automation !== 'none' || !row.is_editable || controlDisabled || !row.applicable}
                            menuPortalTarget={document.querySelector('body')}
                            name="approver"
                        />
                    </div>
                );
            },
        },
        {
            accessor: 'deadline', label: 'Deadline', priority: 1, position: 10, minWidth: 180, sortable: true,
            CustomComponent: ({row}) => {
                const options = {
                    enableTime: false,
                    dateFormat: 'Y-m-d',
                    altFormat: 'd-m-Y',
                    altInput: true,
                    disable: [
                        function (date) {
                            return moment(date).isBefore(moment().subtract(1, 'day'));
                        }
                    ]
                };
                return (
                    <div className="input-group">
                        <Flatpickr
                            className={`form-control flatpickr-date deadline-picker`}
                            options={options}
                            value={row.deadline ?? moment().toDate()}
                            placeholder={row.deadline ? moment(row.deadline).format('DD-MM-YYYY') : moment().format('DD-MM-YYYY')}
                            disabled={!row.is_editable || controlDisabled || !row.applicable || (row.automation === 'technical' && row.status !== 'Not Implemented')}
                            onChange={([deadline]) => handleFieldChange('deadline')(deadline, row)}
                        />
                        <div className="border-start-0">
                            <span className="input-group-text bg-none"
                                  style={{borderTopLeftRadius: 0, borderBottomLeftRadius: 0}}>
                                <i className="mdi mdi-calendar-outline"/>
                            </span>
                        </div>
                    </div>
                );
            },
        },
        {
            accessor: 'frequency', label: 'Frequency', priority: 1, position: 11, minWidth: 140, sortable: true,
            CustomComponent: ({row}) => {
                return (
                    <Select
                        className={`react-select`}
                        classNamePrefix="react-select"
                        onChange={(e) => handleFieldChange('frequency')(e, row)}
                        value={getSelectValue(row, 'frequency', frequencySelectOptions)}
                        isDisabled={!row.is_editable || controlDisabled || !row.applicable || row.automation !== 'none'}
                        menuPortalTarget={document.querySelector('body')}
                        options={frequencySelectOptions}
                    />
                );
            },
        },
        {
            accessor: 'id_separator', label: '', priority: 1, position: 12, minWidth: 120, sortable: false,
            CustomComponent: ({row}) => {
                if (!row.applicable) return <></>;
                return (
                    <Link
                        href={route('compliance-project-control-show', [row.project_id, row.id, 'tasks'])}
                        className="btn btn-sm btn-primary"
                    >
                        Details
                    </Link>
                );
            },
        },
    ];

    const getAndSetPieData = () => {
        axiosFetch.get(
            route('compliance-project-controls-stat', props.project.id)
        )
            .then(function (response) {
                setProjectStat(response.data);
                const pieData = {
                    series: [response.data.implemented, response.data.underReview, response.data.notImplemented, response.data.notApplicable],
                    options: {
                        chart: {
                            type: 'donut',
                        },
                        tooltip: {
                            enabled: true,
                            fillSeriesColor: false,
                            theme: false,
                            style: {
                                fontSize: '15px'
                            }
                        },
                        colors: ["#359f1d", "#5bc0de", "#cf1110", "#6658dd"],
                        labels: ["Implemented", "Under Review", "Not Implemented", "Not Applicable"],
                        legend: {
                            show: true,
                            position: 'bottom',
                            formatter: function (seriesName, opts) {
                                return [seriesName, " - ", opts.w.globals.series[opts.seriesIndex]]
                            }
                        },
                        dataLabels: {
                            enabled: false,
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
                                        value: {
                                            fontSize: "35px",
                                            color: "#6e6b7b",
                                        },
                                        total: {
                                            show: true,
                                            showAlways: true,
                                        },
                                    },
                                }
                            }
                        },
                    },
                }
                setPieData(pieData);
            })
    }

    const handleExportProject = () => {
        dispatch({type: "reportGenerateLoader/show"});
        axiosFetch.get(route('compliance.projects.export', props.project.id), {
            responseType: 'blob',
            params: {
                data_scope: appDataScope
            }
        })
            .then((res) => {
                fileDownload(res.data, `Compliance Project Report ${moment().format('DD-MM-YYYY')}.xlsx`);
            })
            .finally(() => {
                dispatch({type: "reportGenerateLoader/hide"});
            })
    }

    return (
        <AppLayout>
            <ContentLoader show={false}>
                <div id="compliance-project-details-page">
                    <Breadcrumb data={breadcrumbs}/>
                    <FlashMessages/>
                    <div className="row card" id="projects-details">
                        <div className="col-lg-12 card-body" id="project-details-tab-show">
                            <button
                                onClick={handleExportProject}
                                className="btn btn-primary export__risk-btn float-end"
                            >
                                Export
                            </button>
                            <Tabs defaultActiveKey={localStorage["activeTab"] ? "Controls" : "Details"}
                                  className="mb-3">
                                <Tab eventKey="Details" title="Details">
                                    <h5 className="mt-0">
                                        {props.project.name} ( Standard: {props.project.standard} )
                                    </h5>
                                    <p className="mb-0">{props.project.description}</p>
                                </Tab>
                                <Tab eventKey="Controls" title="Controls">
                                    {!controlDisabled &&
                                        <SaveButton disabled={errors.length} loading={isSaving} onClick={saveAndProceed}/>}

                                    <BulkAssignmentModal
                                        tag={tag}
                                        frequencies={frequencySelectOptions}
                                        admins={controlsAdmins}
                                        projectId={props.project.id}
                                        onStart={() => bulkProcessing.current = true}
                                        onFinish={() => bulkProcessing.current = false}
                                        onAssign={() => dataChanged.current = true}
                                    />

                                    <DataTable
                                        columns={columns}
                                        fetchUrl={`/compliance/projects/${props.project.id}/controls-json`}
                                        tag={tag}
                                        refresh={refreshDatatable}
                                        variant="secondary"
                                        disableSelect={(row) => !row.is_editable}
                                        onPageChange={() => {
                                            dataChanged.current = false;
                                            setErrors([]);
                                        }}
                                        resetOnExit
                                        selectable
                                        search
                                    />

                                    {!controlDisabled &&
                                        <SaveButton disabled={errors.length} loading={isSaving} onClick={saveAndProceed}/>}
                                </Tab>
                            </Tabs>
                        </div>
                    </div>
                    <div className="row">
                        <div className="col-lg-12">
                            <h5 className="page-title mb-3 mt-4 fw-bold">Overview</h5>
                        </div>
                    </div>
                    <div className="row">
                        <div className="col-lg-6">
                            <div className="card h-100">
                                <div className="card-body">
                                    <h4 className="header-title">Control Status</h4>
                                    <hr/>
                                    <table id="control-status-table" className="table no-bordered">
                                        <tbody>
                                        <tr>
                                            <td><i data-feather="box" className="text-muted me-2"/>Total Controls:</td>
                                            <td className='text-dark'>
                                                <strong>{projectStat ? projectStat.total : ''}</strong></td>
                                        </tr>
                                        <tr>
                                            <td><i data-feather="delete" className="text-muted me-2"/>Not Applicable:
                                            </td>
                                            <td className='text-dark'>
                                                <strong>{projectStat ? projectStat.notApplicable : ''}</strong></td>
                                        </tr>
                                        <tr>
                                            <td><i data-feather="flag" className="text-muted me-2"/>Implemented
                                                Controls:
                                            </td>
                                            <td className='text-dark'>
                                                <strong>{projectStat ? projectStat.implemented : ''}</strong></td>
                                        </tr>
                                        <tr>
                                            <td><i data-feather="star" className="text-muted me-2"/>Under Review:</td>
                                            <td className='text-dark'>
                                                <strong>{projectStat ? projectStat.underReview : ''}</strong></td>
                                        </tr>
                                        <tr>
                                            <td><i data-feather="x-square" className="text-muted me-2"/>Not Implemented
                                                Controls:
                                            </td>
                                            <td className='text-dark'>
                                                <strong>{projectStat ? projectStat.notImplementedcontrols : ''}</strong>
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div className="col-lg-6">
                            <div className="card h-100">
                                <div className="card-body">
                                    <h4 className="header-title">Implementation Progress</h4>
                                    <hr/>
                                    <div id="chart" className="mx-auto cursor-pointer progress-chart"
                                    >
                                        {pieData &&
                                            <Chart
                                                options={pieData.options}
                                                series={pieData.series}
                                                type="donut"
                                            />
                                        }
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </ContentLoader>
        </AppLayout>
    );
}

export default ProjectDetails;