import React, { Fragment, useEffect, useState } from "react";
import { useSelector, useDispatch } from "react-redux";
import "./riskwizard.css";
import BreadcumbsComponent from "../../../../common/breadcumb/Breadcumb";
import RiskStandardSelection from "./components/RiskStandardSelection";
import ProgressBarComponent from "./components/ProgressBarComponent";
import AppLayout from "../../../../layouts/app-layout/AppLayout";
import { useDidMountEffect } from "../../../../custom-hooks";
import YourselfApproachSection from "./components/YourselfApproachSection";
import AutomatedApproachSection from "./components/AutomatedApproachSection";
import ApproachTab from "./components/ApproachTab";
import { showToastMessage } from "../../../../utils/toast-message";

function RiskSetupWizard(props) {
  const [selectedStandard, setSelectedStandard] = useState({});
  const [selectedApproach, setSelectedApproach] = useState(null);
  const [projectExist, setProjectExist] = useState(false);
  const [wizardCurrentTab, setWizardCurrentTab] = useState("standard");
  const [reachedWizardTab, setReachedWizardTab] = useState(1);
  const [riskStandardsData, setRiskStandardsData] = useState();
  const appDataScope = useSelector(
    (state) => state.appDataScope.selectedDataScope.value
  );
  const dispatch = useDispatch();

  useDidMountEffect(async () => {
    document.title = "Risk Wizard";
    setWizardCurrentTab("standard")
    setReachedWizardTab(1);
  }, [appDataScope]);

  const selectedStandardItem = (e) => {
    setSelectedStandard(e.target.value);
  };

  const handleStandard = (e) => {
    try {
      var url =
        "risks/wizard/check-compliance-projects-exists?standard=" +
        selectedStandard +
        "&data_scope=" +
        appDataScope;
      axiosFetch.get(url).then((res) => {
        const response = res.data;

        if (response.exists == false) {
          setProjectExist(false);
        } else if (response.exists == true) {
          setProjectExist(true);
        }
      });
    } catch (error) {
      console.log("Response error");
    }
  };

  const fetchRiskStandardsData = () => {
    axiosFetch.get(route("risks.wizard.fetch-risk-standards")).then((res) => {
      setRiskStandardsData(res.data[0]);
    });
  }  

  useEffect(()=>{
    (riskStandardsData && riskStandardsData != null) ?? fetchRiskStandardsData()
  })

  // const breadcumbsData = {
  //   title: "Risk Wizard",
  //   breadcumbs: [
  //     {
  //       title: "Risk Management",
  //       href: `${appBaseURL}/risks/dashboard`,
  //     },
  //     {
  //       title: "Risk Setup",
  //       href: "/risks/setup",
  //     },
  //     {
  //       title: "Risk Wizard",
  //       href: "",
  //     },
  //   ],
  // };

  const onError = () => {
    if (props.errors.length > 0) {
      showToastMessage('Risk updated successfully!', 'success');
    }
  };

  const isActiveTab = (tab) => {
    return tab == wizardCurrentTab;
  };

  const goToNextTab = (tab) => {
    if (tab === "approach") {
      handleStandard();
      setWizardCurrentTab(tab);
    } else {
      setWizardCurrentTab(tab);
    }
    if (reachedWizardTab >= 3) return;

      setReachedWizardTab(reachedWizardTab + 1);
  };

  const goToTab = (tab) => {
    setWizardCurrentTab(tab);
  };

  const handleTabClick = (tab, tabIndex) => {
    return reachedWizardTab < tabIndex ? "" : goToTab(tab);
  };

  return (
    // <AppLayout>
      <Fragment>
        {/* <BreadcumbsComponent data={breadcumbsData} /> */}
        {props.errors && onError()}
        <div className="row" id="mainContainerRiskSetupWizard">
        <div className="col-xl-12">
            <button
                className="btn btn-danger back-btn float-end"
                onClick={()=>{props.handleSetupBack()}}
          >
              Back
          </button>
          </div>
          <div className="col-xl-12" id="risk-setup-wizard-page">
            <div className="card">
              <div className="card-body project-box">
                <ul className="nav nav-pills navtab-bg nav-justified risk-setup-nav-wp">
                  <li className="nav-item">
                    <a
                      href="#"
                      id="standard"
                      onClick={() => {
                        goToTab("standard");
                      }}
                      data-toggle="tab"
                      aria-expanded="false"
                      className={`nav-link ${
                        isActiveTab("standard") ? "active" : ""
                      }`}
                    >
                      Choose Standard
                    </a>
                  </li>
                  <li className="nav-item">
                    <a
                      href="#"
                      id="approach"
                      onClick={() => {
                        handleTabClick("approach", 2)
                      }}
                      data-toggle="tab"
                      aria-expanded="true"
                      className={`nav-link ${
                        isActiveTab("approach") ? "active" : ""
                      }`}
                    >
                      Approach
                    </a>
                  </li>
                  <li className="nav-item">
                    <a
                      href="#"
                      id="import"
                      onClick={() => {
                        handleTabClick("import", 3)
                      }}
                      data-toggle="tab"
                      aria-expanded="false"
                      className={`nav-link ${
                        isActiveTab("import") ? "active" : ""
                      }`}
                    >
                      Import
                    </a>
                  </li>
                </ul>

                <div className="tab-content">
                  <ProgressBarComponent reachedWizardTab={reachedWizardTab} />
                  <div
                    className={`tab-pane ${
                      isActiveTab("standard") ? "active" : ""
                    }`}
                    id="standard-tab"
                  >
                    <div className="row">
                      <RiskStandardSelection
                        inputName={"risk-setup-standard"}
                        selectStandard={selectedStandardItem}
                        setReachedWizardTab={setReachedWizardTab}
                        setSelectedApproach={setSelectedApproach}
                        riskStandards={riskStandardsData}
                        currentSelected={selectedStandard}
                      />
                    </div>

                    <button
                        className="btn btn-primary go-to-next-step-btn clearfix mt-2 me-2 float-end d-flex ms-auto"
                        onClick={() => {
                          goToNextTab("approach");
                        }}
                        id="nextBtn"
                        disabled={selectedStandard.length > 2 ? false : true}
                        data-current-tab="1"
                      >
                        Next
                      </button>
                  </div>
                  <div
                    className={`tab-pane ${
                      isActiveTab("approach") ? "active" : ""
                    }`}
                    id="approach-tab"
                  >
                    <div className="row">
                      <ApproachTab
                        setSelectedApproach={setSelectedApproach}
                        currentSelected={selectedApproach}
                        projectExist={projectExist}
                      />
                      <button
                        className="btn btn-primary go-to-next-step-btn clearfix mt-2 me-2 float-end d-flex ms-auto"
                        onClick={() => {
                          goToNextTab("import");
                        }}
                        id="secondNextButton"
                        disabled={selectedApproach != null ? false : true}
                        data-current-tab="2"
                      >
                        Next
                      </button>
                    </div>
                  </div>
                  <div
                    className={`tab-pane ${
                      isActiveTab("import") ? "active" : ""
                    }`}
                    id="import-tab"
                  >
                    {selectedApproach == "Yourself" ? (
                      <YourselfApproachSection
                        wizardCurrentTab={wizardCurrentTab}
                        selectedApproach={selectedApproach}
                        selectedStandard={selectedStandard}
                        projectExist={projectExist}
                        riskProjectId={props.passed_props.project.id}
                      />
                    ) : (
                      <AutomatedApproachSection
                        selectedApproach={selectedApproach}
                        selectedStandard={selectedStandard}
                        projectExist={projectExist}
                        wizardCurrentTab={wizardCurrentTab}
                        riskProjectId={props.passed_props.project.id}
                      />
                    )}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Fragment>
    // </AppLayout>
  );
}

export default RiskSetupWizard;