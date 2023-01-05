import React, {Fragment, useEffect, useRef, useState} from "react";
import AppLayout from "../../../layouts/app-layout/AppLayout";
import CreateProjectWizard from "./components/CreateProjectWizard";
import BreadcumbComponent from "../../../common/breadcumb/Breadcumb";
import FlashMessages from "../../../common/FlashMessages";
import {Inertia} from "@inertiajs/inertia";
import {useSelector} from "react-redux";
import './style.scss';


function ProjectCreatePage(props) {
    const { project } = props;
    const [assignedControls, setAssignedControls] = useState(0);

    const appDataScope = useSelector(state => state.appDataScope.selectedDataScope.value);

    const breadcumbsData = {
        title: "View Projects",
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
                title: `${project.id ? "Update" : "Create"}`,
                href: "#",
            },
        ],
    };

    const dataScopeRef = useRef(appDataScope);
    useEffect(() => {
        if (dataScopeRef.current !== appDataScope) {
            Inertia.get(route("compliance-projects-view"));
        }
    }, [appDataScope]);

    return (
        <Fragment>
            <AppLayout>
                {/* breadcrumbs */}
                <BreadcumbComponent data={breadcumbsData}></BreadcumbComponent>
                {/* end of breadcrumbs */}

                {/* Flash messese */}
                <FlashMessages/>

                <div className="row" id="project-create">
                    <div className="col-xl-12">
                        <div className="card">
                            <div className="card-body">
                                <CreateProjectWizard
                                    project={project}
                                    assignedControls={assignedControls}
                                ></CreateProjectWizard>
                            </div>
                        </div>
                    </div>
                </div>
            </AppLayout>
        </Fragment>
    );
}

export default ProjectCreatePage;
