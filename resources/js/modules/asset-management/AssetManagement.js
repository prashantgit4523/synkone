import React, {useEffect} from 'react';

import {transformDateTime} from "../../utils/date";
import {Inertia} from '@inertiajs/inertia';
import {usePage} from "@inertiajs/inertia-react";
import {useDispatch} from "react-redux";
import fileDownload from "js-file-download";

import AppLayout from '../../layouts/app-layout/AppLayout';
import Breadcrumb from '../../common/breadcumb/Breadcumb';
import AppDataTable from "../../common/custom-datatable/AppDataTable";
import moment from 'moment/moment';
import './style/style.scss';

const AssetManagement = () => {
    const dispatch = useDispatch();

    const {category, should_connect} = usePage().props;
    const breadcrumbs = {
        title: 'Asset Management',
        breadcumbs: []
    }

    const redirectToAssetManagementIntegrations = () => {
        localStorage.setItem('active-category-index', 7);
        Inertia.get(route('integrations.index'));
    }

    useEffect(() => {
        document.title = 'Asset Management';
    }, [])

    const columns = [
        {
            accessor: "name",
            label: "Name",
            priority: 2,
            minWidth: 150,
            sortable: true,
            position: 1,
            CustomComponent: ({row}) => <span>{row.name ?? '-'}</span>
        },
        {
            accessor: "description",
            label: "Description",
            priority: 1,
            minWidth: 220,
            sortable: true,
            position: 2,
            CustomComponent: ({row}) => <span>{row.description ?? '-'}</span>
        },
        {
            accessor: "type",
            label: "Type",
            priority: 2,
            minWidth: 140,
            sortable: true,
            position: 3,
            CustomComponent: ({row}) => <span>{row.type ?? '-'}</span>
        },
        {
            accessor: "classification",
            label: "Classification",
            priority: 3,
            minWidth: 120,
            sortable: true,
            position: 4,
            CustomComponent: ({row}) => <span>{row.classification ?? '-'}</span>
        },
        {
            accessor: "owner",
            label: "Owner",
            priority: 2,
            minWidth: 150,
            sortable: true,
            position: 5,
            CustomComponent: ({row}) => <span>{row.owner ?? '-'}</span>
        },
    ];

    const handleExport = async () => {
        dispatch({type: 'reportGenerateLoader/show'});
        try {
            let response = await axiosFetch({
                url: route('asset-management.export'),
                responseType: 'blob',
            });

            fileDownload(response.data, `Assets Export ${moment().format('DD-MM-YYYY')}.xlsx`);

            dispatch({type: "reportGenerateLoader/hide"});

        } catch (error) {
            dispatch({type: "reportGenerateLoader/hide"});
        }
    }

    return (
        <AppLayout>
            <Breadcrumb data={breadcrumbs}/>
            <div className="position-relative mt-1">
                <div className="row">
                    <div className="col-xl-12">
                        {!should_connect ? (
                            <div className="d-flex justify-content-between align-items-center flex-row-reverse mb-2">
                                <button className="btn btn-primary btn-sm" onClick={handleExport}>Export</button>
                                {category.updated_at &&
                                    <span>Last sync at {transformDateTime(category.updated_at)}</span>}
                            </div>
                        ) : null}
                        <div className="card">
                            <div className="card-body" style={{height: should_connect ? '380px' : 'auto'}}>
                                {should_connect ? (
                                    <div className="overlay">
                                        <div>
                                            <h3>Connect your asset management integration to use this module.</h3>
                                            <button className="btn btn-primary mt-2"
                                                    onClick={redirectToAssetManagementIntegrations}>Connect
                                            </button>

                                        </div>
                                    </div>
                                ) : (
                                    <
                                        AppDataTable
                                        fetchUrl={route('asset-management.get-json-data')}
                                        columns={columns}
                                        tag="assets"
                                        search
                                    />
                                )}

                            </div>
                        </div>
                        {/* <!-- end col --> */}
                    </div>
                </div>
            </div>
        </AppLayout>
    )
}

export default AssetManagement;