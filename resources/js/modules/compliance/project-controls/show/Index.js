import React, { useEffect, useRef } from "react";

import { Inertia } from "@inertiajs/inertia";
import { useSelector, useDispatch } from "react-redux";
import { Tabs, Tab } from "react-bootstrap";
import moment from "moment/moment";

import { usePage } from "@inertiajs/inertia-react";
import AppLayout from "../../../../layouts/app-layout/AppLayout";

import DetailsTab from "./Tabs/DetailsTab";
import TasksTab from "./Tabs/TasksTab";
import FlashMessages from "../../../../common/FlashMessages";

import '../../../global-settings/styles/style.css';
import '../styles/styles.css';
import BreadcumbComponent from '../../../../common/breadcumb/Breadcumb';
import { fetchCampaignCreateData } from "../../../../store/actions/policy-management/campaigns";
import { useStateIfMounted } from "use-state-if-mounted";

const Index = () => {
    const { projectControl, nextReviewDate, activeTabs, ssoIsEnabled, manualOverrideResponsibleRequired } = usePage().props;
    const [activeKey, setActiveKey] = React.useState("details");
    
    const dispatch = useDispatch();

    const [policies, setPolicies] = useStateIfMounted([]);
    const [groups, setGroups] = useStateIfMounted([]);
    const [groupUsers, setGroupUsers] = useStateIfMounted([]);


    const [contributors, setContributors] = React.useState({});

    const projectControlDeadline = projectControl.deadline;
    const taskUnlockingDate = moment(projectControlDeadline)
        .subtract(14, "days")
        .format("YYYY-MM-DD");
    const today = moment().format("YYYY-MM-DD");

    const appDataScope = useSelector(
        (state) => state.appDataScope.selectedDataScope.value
    );

    const dataScopeRef = useRef(appDataScope);
    useEffect(async() => {
        if (dataScopeRef.current !== appDataScope) {
            Inertia.get(route("compliance-projects-view"));
        }

        let { payload } = await dispatch(
            fetchCampaignCreateData({
                data_scope: appDataScope,
                is_awareness : true
            })
        );

        if (payload && payload.success) {
            setPolicies(payload.data.policies);
            setGroups(payload.data.groups);
            setGroupUsers(payload.data.groupUsers);
        }

    }, [appDataScope]);

    useEffect(() => {
        if (["details", "tasks"].includes(activeTabs)) {
            setActiveKey(activeTabs);
        }
        localStorage.setItem('activeTab', 'controls');
    }, []);

    useEffect(() => {
        axiosFetch.get(route('common.contributors'),{params:{editable:projectControl.is_editable}})
        .then(res => {
            setContributors(res.data);
        });
    }, [projectControl.is_editable]);

    useEffect(() => {
        return Inertia.on('before', e => {
            // check where user is goingf
            if (!((e.detail.visit.url.href).includes("/compliance/projects/") || (e.detail.visit.url.href).includes("/documents"))) {
                localStorage.removeItem("controlPerPage");
                localStorage.removeItem("controlCurrentPage");
            }
        });
    });

    const getTaskStatusClass = (status) => {
        switch (status) {
            case "Not Implemented":
                return "task-status-red text-white";
            case "Under Review":
                return "task-status-blue";
            case "Implemented":
                return "task-status-green";
            case "Rejected":
                return "task-status-orange";
        }
    };

    const breadcumbsData = {
        title: "Control Details",
        breadcumbs: [
            {
                title: "Compliance",
                href: route("compliance-dashboard"),
            },
            {
                title: "Projects",
                href: route("compliance-projects-view"),
            },
            {
                title: "Controls",
                href: route('compliance-project-show', [projectControl.project_id, 'controls']),
            },
            {
                title: "Details",
                href: '#',
            },
        ],
    };

    return (
        <AppLayout>
            <BreadcumbComponent data={breadcumbsData}/>
            <FlashMessages/>
            <div className="row">
                <div className="col-xl-12">
                    <div className="card">
                        <div className="card-body position-relative">
                            <Tabs
                                activeKey={activeKey}
                                onSelect={(eventKey) => setActiveKey(eventKey)}
                                unmountOnExit
                            >
                                <Tab eventKey="details" title="Details">
                                    <DetailsTab
                                        contributors = {contributors}
                                        getTaskStatusClass={getTaskStatusClass}
                                    />
                                </Tab>
                                <Tab eventKey="tasks" title="Tasks">
                                        <div
                                        className="status-pill"
                                        id="control-status-badge">

                                        <span
                                            className="badge me-2"
                                            style={{ background: "#00008B", textTransform: 'capitalize' }}>
                                            Automation: {projectControl.automation}
                                        </span>

                                        {projectControl.status !== "Implemented" &&
                                        projectControl.deadline &&
                                        projectControl.automation !== 'document' ? (
                                            <span
                                                className="badge me-2"
                                                style={{ background: "#444" }}>
                                                Deadline: {moment(projectControl.deadline).format("DD-MM-YYYY")}
                                            </span>
                                        ) : null}

                                        {nextReviewDate &&
                                        projectControl.status !== "Implemented" &&
                                        today >= taskUnlockingDate &&
                                        today <= projectControlDeadline ? (
                                            <span
                                                className="badge me-2"
                                                style={{ background: "#444" }}>
                                                Review deadline approaching
                                            </span>
                                        ) : null}
                                        <span
                                            className={`badge ${getTaskStatusClass(
                                                projectControl.status
                                            )}`}>
                                            {projectControl.status}
                                        </span>
                                    </div>
                                     <TasksTab 
                                        active={activeKey === "tasks"} 
                                        campaignTypeFilter="active"
                                        searchQuery=""
                                        policies={policies}
                                        groups={groups}
                                        groupUsers={groupUsers}
                                        controlId={projectControl.id}
                                        ssoIsEnabled={ssoIsEnabled}
                                        manualOverrideResponsibleRequired={manualOverrideResponsibleRequired}
                                    />
                                </Tab>
                            </Tabs>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
};

export default Index;
