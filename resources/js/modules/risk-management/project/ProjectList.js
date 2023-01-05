import React, { Fragment, useEffect, useRef, useState } from "react";
import ProjectCreateBox from "./components/ProjectCreateBox";
import ProjectItem from "./components/ProjectItem";
import AppLayout from "../../../layouts/app-layout/AppLayout";
import "../../compliance/project-list-page/project-list-page.scss";
import Breadcrumb from "../../../common/breadcumb/Breadcumb";
import { useSelector,useDispatch } from "react-redux";
import ContentLoader from "../../../common/content-loader/ContentLoader";
import FlashMessages from "../../../common/FlashMessages";
import { random } from "lodash";
import { storePerPageData,storeCurrentPageData } from "../../../store/actions/risk-management/pagedata";

const breadcumbsData = {
    title: "Project List",
    breadcumbs: [
        {
            title: "Risk Management",
            href: route("risks.projects.index"),
        },
        {
            title: "Projects",
            href: "#",
        },
    ],
};

function ProjectListPage(props) {

    const dispatch = useDispatch();
    
    useEffect(() => {
        document.title = "Risk Projects";
        dispatch(storePerPageData(10));
        dispatch(storeCurrentPageData(1));
    }, []);

    const projectItemRef= useRef();    
    const { authUserRoles } = props;
    const [searchQuery, setSearchQuery] = useState("");
    const [loading, setLoading] = useState(false);

    const handleSearchQueryChange = (event) => {
        projectItemRef.current.filterProjects(event.target.value);
    };


    const renderProjectCreateBox = () => {
        return authUserRoles.includes("Global Admin") ||
            authUserRoles.includes("Risk Administrator") ? (
            <ProjectCreateBox />
        ) : (
            ""
        );
    };

    const toggleLoading = (value) =>{
        setLoading(value);
    }

    return (
        <AppLayout>
            <div id="compliance-project-list-page">
                {/* breadcrumbs */}
                <Breadcrumb data={breadcumbsData}></Breadcrumb>
                {/* end of breadcrumbs */}

                <FlashMessages />

                <div className="row">
                    <div className="col">
                        <div className="float-sm-end">
                            <div className="mx-2 mx-sm-0 ms-sm-3 mb-3">
                            <div className="row align-items-center">
                                <input
                                    type="text"
                                    onChange={handleSearchQueryChange}
                                    name="project_name"
                                    className="form-control form-control-sm"
                                    placeholder={"Search..."}
                                />
                            </div>
                            </div>
                        </div>
                    </div>
                </div>
                <ContentLoader show={loading}>
                    <div className="row" id="projects-wp">
                        {/* project list */}
                        {renderProjectCreateBox()}

                        <ProjectItem
                            toggleLoading={toggleLoading}
                            searchQuery={searchQuery}
                            authUserRoles={authUserRoles}
                            ref={projectItemRef}
                        ></ProjectItem>
                    </div>
                </ContentLoader>
            </div>
        </AppLayout>
    );
}

export default ProjectListPage;
