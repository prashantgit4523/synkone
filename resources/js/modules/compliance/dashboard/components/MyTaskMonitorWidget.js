import React, { Fragment, useEffect, useState } from "react";
import { Link } from "@inertiajs/inertia-react";
import {Card, Col, Row} from "react-bootstrap";

function MyTaskMonitor(props) {
    const { totalTaskDueToday, totalMyTaskPassDue, myAllActiveTasks } = props;
    const [userProjects, setUserProjects] = useState([])
    useEffect(() => {
        axiosFetch.get(route('common.get-all-projects')).then(res => {
                let response = res.data
                if (response.success) {
                    let data = response.projects
                    let data2 = data.map((project) => {
                        return project.value;
                    })
                    setUserProjects(data2)
                    console.log('You are here')
                    console.log(data2)
                }
            }).catch((error) => { console.log(error) })
    }, []);

    return (
        <Fragment>
            <Card>
                <Card.Body>
                    <h4 className="header-title mb-3">My Task Monitor</h4>
                    <Row className="align-items-center">
                        <Col xs={7} className="offset-1 pe-0 d-flex align-items-center">
                        <i data-feather="list" className="text-muted" style={{width:'25px',height:'25px'}}></i>
                                <span className="mx-2 text-dark fa-2x">
                                    {myAllActiveTasks}
                                </span>
                            
                            <h5 className="text-muted">All Upcoming</h5>
                        </Col>
                        <Col xs={4} className="ps-0">
                            <Link
                                href={route("compliance.tasks.all-active")}
                                className="btn btn-light width-sm btn-rounded go-btn"
                                method="get"
                                data={{
                                    selected_projects: userProjects.join(","),
                                }}
                            >
                                Go
                            </Link>
                        </Col>
                    </Row>
                    <hr />
                    <Row className="align-items-center">
                        <Col xs={7} className="offset-1 pe-0 d-flex align-items-center">
                        <i data-feather="help-circle" className="text-muted" style={{width:'25px',height:'25px'}}></i>
                             <span className="mx-2 text-dark fa-2x">
                                    {totalTaskDueToday}
                             </span>
                            
                            <h5 className="text-muted">Due Today</h5>
                        </Col>
                        <Col xs={4} className="ps-0">
                            <Link
                                href={route("compliance.tasks.due-today")}
                                className="btn btn-light width-sm btn-rounded go-btn"
                                method="get"
                                data={{
                                    selected_projects: userProjects.join(","),
                                }}
                            >
                                Go
                            </Link>
                        </Col>
                    </Row>
                    <hr />
                    <Row className="align-items-center">
                        <Col xs={7} className="offset-1 pe-0 d-flex align-items-center">
                        <i data-feather="x-circle" className="text-muted" style={{width:'25px',height:'25px'}}></i>
                               <span className="mx-2 text-dark fa-2x">
                                    {totalMyTaskPassDue}
                                </span>
                            
                            <h5 className="text-muted">Past Due</h5>
                        </Col>
                        <Col xs={4} className="ps-0">
                            <Link
                                href={route("compliance.tasks.pass-today")}
                                className="btn btn-light width-sm btn-rounded go-btn"
                                method="get"
                                data={{
                                    selected_projects: userProjects.join(","),
                                }}
                            >
                                Go
                            </Link>
                        </Col>
                    </Row>
                </Card.Body>
            </Card>
            {/*<div className="card second">*/}
            {/*    <div className="card-body p-0">*/}
            {/*        <div className="title">*/}
            {/*            <h4 className="header-title">My Task Monitor</h4>*/}
            {/*        </div>*/}
            {/*        <div className="task d-flex justify-content-around">*/}
            {/*            {" "}*/}
            {/*            <div className="boxx">*/}
            {/*                <i className="fas fa-tasks fa-2x">*/}
            {/*                    <span className="mx-2 text-muted">*/}
            {/*                        {myAllActiveTasks}*/}
            {/*                    </span>*/}
            {/*                </i>*/}
            {/*                <hr />*/}
            {/*                <h5 className="text-muted">All Upcoming</h5>*/}
            {/*                <hr />*/}
            {/*                <Link*/}
            {/*                    href={route("compliance.tasks.all-active")}*/}
            {/*                    className="btn btn-primary width-sm btn-rounded go-btn"*/}
            {/*                    method="get"*/}
            {/*                >*/}
            {/*                    Go*/}
            {/*                </Link>*/}
            {/*            </div>*/}
            {/*            <div className="boxx">*/}
            {/*                <i className="fas fa-info-circle fa-2x">*/}
            {/*                    <span className="mx-2 text-muted">*/}
            {/*                        {totalTaskDueToday}*/}
            {/*                    </span>*/}
            {/*                </i>*/}
            {/*                <hr />*/}
            {/*                <h5 className="text-muted">Due Today</h5>*/}
            {/*                <hr />*/}
            {/*                <Link*/}
            {/*                    href={route("compliance.tasks.due-today")}*/}
            {/*                    className="btn btn-primary width-sm btn-rounded go-btn"*/}
            {/*                    method="get"*/}
            {/*                >*/}
            {/*                    Go*/}
            {/*                </Link>*/}
            {/*            </div>*/}
            {/*            <div className="boxx third-box">*/}
            {/*                <i className="fas fa-times-circle fa-2x">*/}
            {/*                    <span className="mx-2 text-muted">*/}
            {/*                        {totalMyTaskPassDue}*/}
            {/*                    </span>*/}
            {/*                </i>*/}
            {/*                <hr />*/}
            {/*                <h5 className="text-muted">Past Due</h5>*/}
            {/*                <hr />*/}
            {/*                <Link*/}
            {/*                    href={route("compliance.tasks.pass-today")}*/}
            {/*                    className="btn btn-primary width-sm btn-rounded go-btn"*/}
            {/*                    method="get"*/}
            {/*                >*/}
            {/*                    Go*/}
            {/*                </Link>*/}
            {/*            </div>*/}
            {/*        </div>{" "}*/}
            {/*    </div>*/}
            {/*</div>*/}
        </Fragment>
    );
}

export default MyTaskMonitor;
