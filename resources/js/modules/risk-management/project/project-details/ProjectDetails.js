import React, {Fragment, useEffect, useState, useRef} from 'react';
import {useDispatch, useSelector} from "react-redux";
import AppLayout from '../../../../layouts/app-layout/AppLayout';
import Breadcrumb from '../../../../common/breadcumb/Breadcumb';
import ContentLoader from '../../../../common/content-loader/ContentLoader';
import Tabs from 'react-bootstrap/Tabs';
import Tab from 'react-bootstrap/Tab';
import './style.scss';
import "flatpickr/dist/themes/light.css";
import feather from "feather-icons";
import {Inertia} from '@inertiajs/inertia';
import FlashMessages from '../../../../common/FlashMessages';
import RiskRegister from '../../risk-register/RiskRegister';
import RiskRegisterTab from '../../risk-register/RiskRegisterTab';
import RiskSetup from '../../risk-setup/RiskSetup';
import RiskRegisterCreate from '../../risk-register/components/RiskRegisterCreate';
import RiskRegisterShow from '../../risk-register/components/RiskRegisterShow';
import { storePerPageData,storeCurrentPageData } from '../../../../store/actions/risk-management/pagedata';

function ProjectDetails(props) {
    const appDataScope = useSelector(state => state.appDataScope.selectedDataScope.value);
    const [riskAddView, setRiskAddView] = useState(false);
    const [riskEditData, setRiskEditData] = useState(null);
    const [riskEditAction, setRiskEditAction] = useState(false);
    const [riskRegisterTabKey, setRiskRegisterTabKey] = useState(new Date());
    const dataScopeRef = useRef(appDataScope);
    const dispatch = useDispatch();
    useEffect(() => {
        document.title = "Project Details";
        if (dataScopeRef.current !== appDataScope) {
            Inertia.get(route("risks.projects.index"));
        }
        feather.replace();
    }, [appDataScope]);

    const breadcumbsData = {
        "title": "Project Details",
        "breadcumbs": [
            {
                "title": "Risk",
                "href": route('risks.projects.index')
            },
            {
                "title": "Projects",
                href: route("risks.projects.index"),
            },
            {
                "title": "Details",
                "href": "#"
            },
        ]
    }

    useEffect(() => {
        return Inertia.on('before', e => {
            if (!((e.detail.visit.url.href).includes("/risks") || e.detail.visit.url.href.includes("/risk-register"))) {
                dispatch(storePerPageData(10));
                dispatch(storeCurrentPageData(1));
            }
        });
    });

    useEffect(async () => {
       let url = new URL(window.location.href);
       let risk_id = url.searchParams.get("risk");
       if(risk_id){
            let data=[];
            data['id']=risk_id;
            showRiskAddView(true,data);
            setEditAction(false);
       }


    }, [appDataScope]);

    const showRiskAddView = (value,risk) => {
        setRiskAddView(value);
        setRiskEditData(risk);
        window.scrollTo(0, 0);
    }

    const setEditAction =(value)=>{
        setRiskEditAction(value);
        window.scrollTo(0, 0);
    }

    const setRiskEditDataFromUrl = (risk) =>{
        setRiskEditData(risk);
    }

    return (
        <AppLayout>
            <ContentLoader show={false}>
                <div id="risk-project-details-page">
                    {/* breadcrumbs */}
                    <Breadcrumb data={breadcumbsData}></Breadcrumb>
                    <FlashMessages/>
                    {/* end of breadcrumbs */}
                    <div className="row card" id="projects-details">
                        <div className="col-lg-12 card-body" id="project-details-tab-show">
                        {/* <h5 className="">
                            {props.project.name} ( {props.project.description} )
                        </h5> */}
                        
                        {/* <button className="btn btn-primary export__risk-btn float-end"
                                >Export</button> */}
                            <Tabs defaultActiveKey={props.activeTab?props.activeTab:"RiskRegister"} onSelect={(active)=>{if(active=='RiskRegister'){setRiskRegisterTabKey(new Date())}}} className="mb-3">
                                {/* <Tab eventKey="Dashboard" title="Dashboard">
                                    <h5 className="mt-0">
                                        {props.project.name}
                                    </h5>
                                    <p className="mb-0">{props.project.description}</p>
                                </Tab> */}
                                <Tab eventKey="RiskRegister" title="Risk Register">
                                    {!riskAddView? 
                                    <RiskRegisterTab passed_props={props} showRiskAddView={showRiskAddView} key={riskRegisterTabKey}></RiskRegisterTab>
                                    :
                                        riskEditData && !riskEditAction?
                                            <RiskRegisterShow passed_props={props} showRiskAddView={showRiskAddView} setEditAction={setEditAction} id={riskEditData.id} setRiskEditDataFromUrl={setRiskEditDataFromUrl}></RiskRegisterShow>
                                            :
                                        riskEditData && riskEditAction ?
                                            <RiskRegisterCreate passed_props={props} showRiskAddView={showRiskAddView} setEditAction={setEditAction} id={riskEditData.id} risk={riskEditData} ></RiskRegisterCreate>
                                            :
                                            <RiskRegisterCreate passed_props={props} showRiskAddView={showRiskAddView} setEditAction={setEditAction}></RiskRegisterCreate>
                                    }
                                </Tab>
                                <Tab eventKey="RiskSetup" title="Risk Setup">
                                    {/* <RiskSetup risksAffectedProperties={props.risksAffectedProperties} riskCategories={props.riskCategories} riskMatrixLikelihoods={props.riskMatrixLikelihoods}></RiskSetup> */}
                                    <RiskSetup passed_props={props}></RiskSetup>
                                </Tab>
                            </Tabs>
                        </div>
                    </div>
                    
                </div>
            </ContentLoader>
        </AppLayout>
    );
}

export default ProjectDetails;