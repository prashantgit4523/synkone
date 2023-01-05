import React, { Fragment, useEffect, useState, forwardRef, useImperativeHandle } from "react";
import { useDispatch, useSelector } from "react-redux";
import ProjectActionOption from "./ProjectActionOption";
import { fetchProjectList } from "../../../../store/actions/risk-management/project";
import { useStateIfMounted } from "use-state-if-mounted";
import { Link } from "@inertiajs/inertia-react";

function ProjectItem(props, ref) {
    const { searchQuery, authUserRoles } = props;
    const [showProjectActionOptions, setShowProjectActionOptions] =
        useStateIfMounted(false);
    const dispatch = useDispatch();
    const appDataScope = useSelector(
        (state) => state.appDataScope.selectedDataScope.value
    );
    const [projects, setProjects] = useState([]);
    const [filteredProjects, setFilteredProjects] = useState([]);


    /* data scope update */
    useEffect(() => {
        loadProjects();
    }, [appDataScope, searchQuery]);

    useImperativeHandle(ref, () => ({
        filterProjects
    }));

    const filterProjects = (value) => {
        console.log(value);
        var filtered_projects = [...projects].filter(function (project) {
            return project.name.toUpperCase().startsWith(value.toUpperCase());
        });
        setFilteredProjects(filtered_projects);
    }

    useEffect(() => {
        if (
            authUserRoles.includes("Global Admin") ||
            authUserRoles.includes("Risk Administrator")
        ) {
            setShowProjectActionOptions(true);
        } else {
            setShowProjectActionOptions(false);
        }
    }, []);

    const loadProjects = async () => {
        props.toggleLoading(true);
        const response = await axiosFetch.get('risks/projects/list', {
            project_name: searchQuery,
            data_scope: appDataScope,
        })
        if (response.data.success) {
            setProjects(response.data.data);
            setFilteredProjects(response.data.data);
        }

        props.toggleLoading(false);
    };

    return (
        <Fragment>
            {filteredProjects.map(function (project, index) {

                return (
                    <div className="col-lg-4 col-sm-6" key={index}>
                        <div className="card">
                            <div className="card-body project-box project-div">
                                {showProjectActionOptions ? (
                                    <ProjectActionOption
                                        loadProjects={loadProjects}
                                        project={project}
                                    ></ProjectActionOption>
                                ) : (
                                    ""
                                )}
                                {/* <a href={`${appBaseURL}/compliance/projects/${project.id}/show`} className="text-dark"> */}
                                <Link
                                    href={route(
                                        "risks.projects.project-show",
                                        project.id
                                    )}
                                    className="text-dark"
                                >
                                    <div>
                                        {/* Title*/}
                                        <h4 className="mt-0 sp-line-1">
                                            {decodeHTMLEntity(project.name)}
                                        </h4>
                                        <p className="text-muted text-uppercase"><i className="mdi mdi-format-list-bulleted-type"></i> <small><b>{project.risk_registers_count}</b>{" "}
                                            Registered Risks</small></p>
                                        <p />
                                        <p className="text-muted font-13 mb-3 sp-line-2">
                                            {decodeHTMLEntity(project.description)}
                                        </p>

                                        {/* Task info*/}
                                        <p className="mb-1">
                                            {project.risk_level_count.map(function (level, index) {
                                                return (
                                                    <span className="pe-2 text-nowrap mb-2 d-inline-block" key={index} >
                                                        {/* <i className="mdi mdi-format-list-bulleted-type text-muted"></i> */}
                                                        <span className="badge" style={{ backgroundColor: level.color, lineHeight: 'inherit', color: 'black' }}>{level.name}:{" "}
                                                            <b>{level.risk_count}</b></span>
                                                    </span>
                                                );
                                            })}

                                        </p>
                                        {/* Progress*/}
                                        <p className="mb-2 fw-bold">
                                            Risks Closed{" "}
                                            <span className="float-end">
                                                {project.risk_closed_count}
                                            </span>
                                        </p>
                                        <div
                                            className="progress mb-1"
                                            style={{ height: 7 }}
                                        >
                                            <div
                                                className="progress-bar"
                                                role="progressbar"
                                                aria-valuenow={
                                                    50
                                                }
                                                aria-valuemin={0}
                                                aria-valuemax={100}
                                                style={{
                                                    width: project.risk_closed + `%`,
                                                }}
                                            ></div>
                                            {/* /.progress-bar .progress-bar-danger */}
                                        </div>
                                        {/* /.progress .no-rounded */}
                                        {/* </a> */}
                                    </div>
                                </Link>
                            </div>{" "}
                        </div>
                    </div>
                );
            })}
        </Fragment>
    );
}

export default forwardRef(ProjectItem);
