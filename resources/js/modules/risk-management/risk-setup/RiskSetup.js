import React,{Fragment,useEffect,useState} from 'react';
import './styles/RiskSetup.scss';
import BreadcumbsComponent from '../../../common/breadcumb/Breadcumb';
import ProjectBox from './components/ProjectBox';
import AppLayout from '../../../layouts/app-layout/AppLayout';
import ManualRiskSetup from './manual/ManualRiskSetup';
import RiskSetupWizard from './wizard/RiskSetupWizard';

function RiskSetup(props) {
    const [showSetupComponent, setShowSetupComponent] = useState(false);
    const [setupMethod, setSetupMethod] = useState(0);

    useEffect(() => {
        document.title = "Risk Project";
        if(props.passed_props.activeTab=="RiskSetup"){
            if(props.passed_props.flash.error || props.passed_props.flash.success){
                selectSetupMethod(0);
            }
        }
    }, [props]);

    const projects = [
        {
            title: "Manual Import",
            description:
                "Manual import allows you to manually upload  a large number of risks using a CSV template. This  is great for organizations who want to bulk upload risks.",
            href: route("risks.manual.setup"),
        },
        {
            title: "Wizard Import",
            description:
                "The wizard allows you to automatically generate risks based on compliance projects and to choose risks based on international standards. ",
            href: route("risks.wizard.setup"),
        },
    ];

    const selectSetupMethod= (value)=>{
        setShowSetupComponent(true);
        setSetupMethod(value);
    }

    const handleSetupBack = ()=>{
        setShowSetupComponent(false);
    }

    return (
        // <AppLayout>
            <Fragment>
                {/* <BreadcumbsComponent data={breadcumbsData} /> */}
                {showSetupComponent ?
                    // <ManualRiskSetup risksAffectedProperties={props.risksAffectedProperties} riskCategories={props.riskCategories} riskMatrixLikelihoods={props.riskMatrixLikelihoods}></ManualRiskSetup>
                           setupMethod == 0 ?
                                <ManualRiskSetup passed_props={props.passed_props} handleSetupBack={handleSetupBack}></ManualRiskSetup>
                                :
                                <RiskSetupWizard passed_props={props.passed_props} handleSetupBack={handleSetupBack} ></RiskSetupWizard>
                    :
                    <ProjectBox projectsData={projects} selectSetupMethod={selectSetupMethod} />
                }
            </Fragment>
        // </AppLayout>
    );
}

export default RiskSetup;
