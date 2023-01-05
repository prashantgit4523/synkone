import React, { Fragment, useEffect, useState, useRef, createRef } from "react";
import { useDispatch, useSelector } from "react-redux";
import AppLayout from "../../layouts/app-layout/AppLayout";
import fileDownload from "js-file-download";
import DepartmentFilter from "./components/department-filter/DepartmentFilter";
import { useDidMountEffect } from "../../custom-hooks";
import { Link, usePage } from '@inertiajs/inertia-react';
import ControlCard from "./components/control/ControlCard";
import StandardFilter from "./components/standard-filter/StandardFilter";
import { fetchControlList } from '../../store/actions/controls/control';
import { Row } from "react-bootstrap";
import moment from 'moment/moment';

import "./styles/style.scss";

function Dashboard(props) {
    const { integrationConnected, samaStandardId } = props;
    const [isSamaStandard, setIsSamaStandard] = useState(false);
    const standardFilterRef = useRef(null);
    const [kpiControlsKey, setKpiControlsKey] = useState(new Date());
    const [kpiControls, setKpiControls] = useState([]);
    const { controls } = useSelector(
        (state) => state.controlReducer.controlsReducer
    );
    const appDataScope = useSelector(
        (state) => state.appDataScope.selectedDataScope.value
    );
    const selectedDepartments = useSelector(
        (store) =>
            store.commonReducer.departmentFilterReducer.selectedDepartment
    );
    const { selectedStandards } = useSelector(
        (store) => store.controlReducer.standardFilterReducer
    );
    const dispatch = useDispatch();
    const departmentFilterRef = useRef(null);

    useEffect(() => {
        document.title = "KPI Dashboard";
    }, []);

    useEffect(() => {
        if (JSON.stringify(controls) != JSON.stringify(kpiControls)) {
            setKpiControlsKey(new Date());
            setKpiControls(controls);
        }
    }, [controls]);

    useEffect(async () => {
        if (selectedStandards.length > 0) {
            dispatch(
                fetchControlList({
                    departments: selectedDepartments,
                    standards: selectedStandards
                })
            );
            setIsSamaStandard(selectedStandards[0] == samaStandardId)
        }

    }, [selectedDepartments, selectedStandards]);

    const generateReport = async () => {
        const URL = route("kpi.index-dashboard-data-export");

        try {
            /* showing report generate loader */
            dispatch({ type: "reportGenerateLoader/show" });

            let response = await axiosFetch({
                url: URL,
                method: "Post",
                data: {
                    data_scope: appDataScope,
                    departments: selectedDepartments,
                    standards: selectedStandards,
                    controls: controls
                },
                responseType: "blob", // Important
            });

            fileDownload(response.data, `Kpi Control ${moment().format('DD-MM-YYYY')}.pdf`);

            /* hiding report generate loader */
            dispatch({ type: "reportGenerateLoader/hide" });
        } catch (error) {
            /* hiding report generate loader */
            dispatch({ type: "reportGenerateLoader/hide" });
        }
    };

    return (
        <AppLayout>
            {
                integrationConnected &&
                <div id="control-dashboard-page">
                    {/* <!-- breadcrumbs --> */}
                    <div className="row">
                        <div className="col-12">
                            <div className="page-title-box">
                                <div className="page-title-right">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            generateReport();
                                        }}
                                        className="btn btn-primary risk-export_btn width-md"
                                    >
                                        Export to PDF
                                    </button>
                                </div>
                                <div
                                    className="page-title-right"
                                    style={{ marginRight: "10px" }}
                                >
                                    <StandardFilter ref={standardFilterRef}></StandardFilter>
                                </div>
                                <div
                                    className="page-title-right"
                                    style={{ marginRight: "10px" }}
                                >
                                    <DepartmentFilter
                                        ref={departmentFilterRef}
                                    ></DepartmentFilter>
                                </div>

                                <h4 className="page-title">Dashboard</h4>
                            </div>
                        </div>
                    </div>
                    <Row key={kpiControlsKey}>
                        {/* <div className="col-md-12" style={{ minHeight:"100px",minWidth:"100%",margin:"50px auto"}} >
                    <ContentLoader show={loading}></ContentLoader>
                </div> */}
                        {/* <!-- end of breadcrumbs --> */}
                        {kpiControls && kpiControls.map(function (control, index) {
                            return (
                                <ControlCard control={control} key={control.id} isSamaStandard={isSamaStandard}></ControlCard>
                            );
                        })
                        }
                        {kpiControls.length == 0 &&
                            <h4>No data to show.</h4>
                        }
                    </Row>
                </div>
            }

            {!integrationConnected &&
                <div className="overlay">
                    <div>
                        {/*<h3>Coming Soon</h3>*/}
                        <h3>Connect your integrations to use this module.</h3>
                        <Link className="btn btn-primary mt-2" href={route('integrations.index')}>Connect</Link>
                    </div>
                </div>
            }
        </AppLayout>
    );
}

export default Dashboard;
