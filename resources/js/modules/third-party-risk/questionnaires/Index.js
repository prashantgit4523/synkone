import React, {useEffect, useState} from 'react';

import {useSelector} from "react-redux";
import {Inertia} from "@inertiajs/inertia";
import {Link} from "@inertiajs/inertia-react";

import AppLayout from "../../../layouts/app-layout/AppLayout";
import DataTable from "../../../common/custom-datatable/AppDataTable";
import BreadcumbComponent from "../../../common/breadcumb/Breadcumb";
import FlashMessages from "../../../common/FlashMessages";
import {transformDate} from "../../../utils/date";
import CustomDropdown from "../../../common/custom-dropdown/CustomDropdown";
import '../style/questionnaire-list.scss';

const breadcrumbs = {
    title: 'View Questionnaires',
    breadcumbs: [
        {
            "title": "Third Party Risk",
            "href": ""
        },
        {
            "title": "Questionnaires",
            "href": route('third-party-risk.questionnaires.index')
        }
    ]
};

const Index = () => {
    const [refreshToggle, setRefreshToggle] = useState(false);
    const fetchUrl = route('third-party-risk.questionnaires.get-json-data');
    const appDataScope = useSelector(state => state.appDataScope.selectedDataScope.value);

    useEffect(() => {
        document.title = "Third Party Risk Questionnaires";
    }, []);

    const handleDeleteQuestionnaire = id => {
        AlertBox({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            confirmButtonColor: '#f1556c',
            allowOutsideClick: false,
            icon: 'warning',
            iconColor: '#f1556c',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!'
        }, function (result) {
            if (result.isConfirmed) {
                Inertia.post(route('third-party-risk.questionnaires.destroy', [id]), {_method: 'delete'}, {
                    onFinish: () => setRefreshToggle(!refreshToggle)
                });
            }
        })
    }

    const columns = [
        {accessor: 'name', label: 'Name', position: 1, priority: 2, sortable: true, minWidth: 140},
        {accessor: 'version', label: 'Version', position: 2, priority: 2, sortable: true, minWidth: 80},
        {
            accessor: 'questions_count',
            label: 'Questions',
            position: 3,
            sortable: true,
            priority: 1,
            minWidth: 180,
            CustomComponent: ({row}) => (<span className="badge bg-info">{row.questions_count} questions</span>)
        },
        {
            accessor: 'created_at',
            label: 'Created On',
            position: 4,
            sortable: true,
            priority: 1,
            minWidth: 140,
            CustomComponent: ({row}) => (<span>{transformDate(row.created_at)}</span>)
        },
        {
            accessor: 'actions',
            label: 'Actions',
            position: 5,
            priority: 3,
            sortable: false,
            minWidth: 50,
            CustomComponent: ({row}) => (
                <
                    CustomDropdown
                    button={<i className="mdi mdi-dots-horizontal m-0 text-muted h3" />}
                    dropdownItems={
                        <>
                            <Link

                                className="dropdown-item d-flex align-items-center"
                                href={route('third-party-risk.questionnaires.questions.index', [row.id])}
                            >
                                <i className="mdi mdi-eye-outline font-18 me-1"/> View
                            </Link>

                            <Link

                                className="dropdown-item d-flex align-items-center"
                                href={route('third-party-risk.questionnaires.duplicate.index', [row.id])}
                            >
                                <i className="mdi mdi-content-copy font-18 me-1"/> Duplicate Questionnaire
                            </Link>
                            {!row.is_default ? (
                                <>
                                    <Link
                                        className="dropdown-item d-flex align-items-center"
                                        href={route('third-party-risk.questionnaires.questions.create', [row.id])}
                                    >
                                        <i className="mdi mdi-plus-box-outline font-18 me-1"/> Add Question
                                    </Link>
                                    <Link
                                        className="dropdown-item d-flex align-items-center"
                                        href={route('third-party-risk.questionnaires.edit', [row.id])}
                                    >
                                        <i className="mdi mdi-pencil-outline font-18 me-1"/> Edit Information
                                    </Link>
                                    <button
                                        className="dropdown-item d-flex align-items-center"
                                        onClick={() => handleDeleteQuestionnaire(row.id)}
                                    >
                                        <i className="mdi mdi-delete-outline font-18 me-1"/> Delete
                                    </button>
                                </>
                            ): null }
                        </>
                    }
                />
            )
        }
    ];

    useEffect(() => {
        setRefreshToggle(!refreshToggle);
    }, [appDataScope]);

    return (
        <AppLayout>
            <BreadcumbComponent data={breadcrumbs}/>
            <FlashMessages/>
            <div className="row">
                <div className="col-12 questionnaire-list">
                    <div className='card'>
                        <div className="card-body">
                            
                            <h4 className="header-title float-sm-start">Manage Questionnaires</h4>
                            <Link
                                href={route('third-party-risk.questionnaires.create')}
                                className="btn btn-sm btn-primary waves-effect waves-light float-sm-end mb-4 d-block d-sm-inline-block mt-2 mt-sm-0"
                            >
                                <i className="mdi mdi-plus-circle" title="Add New Questionnaire"/>&nbsp;
                                Add New Questionnaire
                            </Link>
                            <div style={{clear:'both'}}></div>
                            <DataTable
                                columns={columns}
                                fetchUrl={fetchUrl}
                                refresh={refreshToggle}
                                tag="third-party-risk-questionnaires"
                                search
                                emptyString='No data found'
                            />
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    )
};

export default Index;
