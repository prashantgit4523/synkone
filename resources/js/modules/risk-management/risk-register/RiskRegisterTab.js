import React, { useEffect, useState } from "react";

import AppLayout from "../../../layouts/app-layout/AppLayout";

import RiskRegisterFilters from "./components/RiskRegisterFilters";
import RiskRegisterActions from "./components/RiskRegisterActions";
import RiskCategories from "./components/RiskCategories";

import "./styles/style.scss";
import "react-toastify/dist/ReactToastify.css";
import { ToastContainer } from "react-toastify";
import FlashMessages from "../../../common/FlashMessages";
import Breadcrumb from "../../../common/breadcumb/Breadcumb";
import Dashboard from "../project/components/Dashboard";
import RiskItemsSection from "./components/RiskItemsSection";
import moment from "moment/moment";

const RiskRegisterTab = (props) => {
    const [searchTerm, setSearchTerm] = useState("");
    const [filters, setFilters] = useState({
        search_term: "",
        only_incomplete: false,
    });

    const [dateToFilter, setDateToFilter] = useState(moment(new Date()).format('YYYY-MM-DD'));
    const dashboardToThis = (filterDate) => {
        setDateToFilter(filterDate);
    }
    
    const breadcumbsData = {
        "title": "View Risk",
        "breadcumbs": [
            {
                "title": "Risk Management",
                "href": route('risks.dashboard.index')
            },
            {
                "title": "Risk Register",
                "href": "#"
            }
        ]
    }
    const handleCheck = () =>
        setFilters({ ...filters, only_incomplete: !filters.only_incomplete });
    const handleTermChange = (e) => setSearchTerm(e.target.value);

    useEffect(() => {
        document.title = "Risk Project";
        if(searchTerm.length >= 3) return setFilters({...filters, search_term: searchTerm});
        if(filters.search_term !== '' && searchTerm.length <3) setFilters({...filters, search_term: ''});
    }, [searchTerm]);

    return (
        // <AppLayout>
            <div id="risk-register-page">
               
                {/* <Breadcrumb data={breadcumbsData}></Breadcrumb> */}
                {/* <FlashMessages /> */}
                <Dashboard passed_props={props.passed_props} dashboardToThis={dashboardToThis}></Dashboard>
                <div className="row">
                    {/* <div className="col-xl-12">
                        <div className="card"> */}
                            <div className="project-box">
                                <RiskRegisterActions showRiskAddView={props.showRiskAddView} passed_props={props.passed_props} selectedDate={dateToFilter} />
                                <RiskRegisterFilters
                                    searchTerm={searchTerm}
                                    onTermChange={handleTermChange}
                                    onlyIncomplete={filters.only_incomplete}
                                    onCheck={handleCheck}
                                    title="Risk Register"
                                />
                                <div id="risk-by-category-section">
                                    <RiskItemsSection showRiskAddView={props.showRiskAddView} primaryFilters={filters} categoryId={1} project_id={props.passed_props.project.id} prevPage={"riskRegiter"} dateToFilter={dateToFilter}  />
                                </div>
                            </div>
                        {/* </div>
                    </div> */}
                </div>
            </div>
        // </AppLayout>
    );
};

export default RiskRegisterTab;
