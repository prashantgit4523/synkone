import React, {Fragment, useEffect, useState} from 'react';
import BreadcumbsComponent from '../../common/breadcumb/Breadcumb';
import './controls.css';
import DataTable from '../../common/custom-datatable/AppDataTable';
import {Link} from '@inertiajs/inertia-react'
import AppLayout from '../../layouts/app-layout/AppLayout';
import {Modal} from "react-bootstrap";
import fileDownload from "js-file-download";

function LinkedControlEvidences(props) {
    const {project, projectControlId, linkedToControlId} = props;
    
    //text evidence modal
    const [textEvidenceModalShow, setTextEvidenceModalShow] = useState(false);
    const [textEvidenceHeading, setTextEvidenceHeading] = useState('');
    const [textEvidenceText, setTextEvidenceText] = useState('');

    const [jsonEvidence, setJsonEvidence] = useState('');
    const [jsonEvidenceModalShow, setJsonEvidenceModalShow] = useState(false);

    useEffect(() => {
        document.title = "Linked Control Evidences";
    }, []);

    const downloadEvidence = async (row) => {
        try {
            let {
                data,
                headers
            } = await axiosFetch.get(route('compliance-project-control-evidences-download', [project.id, row.project_control_id, row.id]),
                {
                    responseType: "blob", // Important
                });
            let fileNameStr = headers["content-disposition"].split("filename=")[1];
            let fileName = fileNameStr.substring(1, fileNameStr.length - 1);
            
            const extension = fileName.split('.').reverse()[0];
            const isValid = ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'png', 'jpeg', 'gif', 'pdf', 'msg', 'eml'].includes(extension);
            if(isValid){
                fileDownload(data, fileName);
            }
            else{
                fileDownload(data, fileNameStr);
            }
        } catch (error) {
            console.log(error);
        }
    };

    const handleTextEvidence = (row) => {
        setTextEvidenceHeading(row.name);
        setTextEvidenceText(row.text_evidence);
        setTextEvidenceModalShow(true);
    }

    const handleJsonEvidence = (row) => {
        setJsonEvidence(row.text_evidence);
        setJsonEvidenceModalShow(true);
    }

    const fetchURL = route('project-control-linked-controls-evidences', [project.id, projectControlId, linkedToControlId])

    const columns = [
        {
            accessor: 'name', label: 'Name', priorityLevel: 1, position: 1, minWidth: 150, sortable: false,
            CustomComponent: ({row}) => {
                return (
                    <Fragment>
                        {
                            row.type == 'control' ?
                                <>
                                    This control is linked to <Link
                                    href={route('project-control-linked-controls-evidences-view', [project.id, row.path, row.project_control_id])}>{row.name}</Link>
                                </> :
                                <>{row.name}</>
                        }
                    </Fragment>
                );
            },
        },
        {accessor: 'type', label: 'Type', priorityLevel: 1, position: 2, minWidth: 150, sortable: false},
        {accessor: 'deadline', label: 'Task Deadline', priorityLevel: 1, position: 3, minWidth: 50, sortable: false},
        {accessor: 'created_date', label: 'Created On', priorityLevel: 1, position: 4, minWidth: 150, sortable: false},
        {
            accessor: 'action', label: 'Actions', priorityLevel: 0, position: 5, minWidth: 150, sortable: false,
            CustomComponent: ({row}) => {
                return (
                    <Fragment>
                        {
                            row.type === 'document' &&
                            <a className='btn btn-secondary btn-xs waves-effect waves-light mb-1' title='Download'
                               onClick={() => downloadEvidence(row)}>
                                <i className='fe-download' style={{fontSize: '20px', color: 'white'}}/>
                            </a>
                        }
                        {
                            row.type === 'control' &&
                            <Link className='btn btn-secondary btn-xs waves-effect waves-light mb-1'
                                  title='Linked Control'
                                  href={route('project-control-linked-controls-evidences-view', [project.id, row.path, row.project_control_id])}>
                                <i className='fe-link' style={{fontSize: '20px'}}/>
                            </Link>
                        }
                        {
                            row.type === 'link' &&
                            <a href={row.path} className='btn btn-secondary btn-xs waves-effect waves-light mb-1'
                               target='_blank' title='External Link'>
                                <i className='fe-eye' style={{fontSize: '20px'}}/>
                            </a>
                        }
                        {
                            row.type === 'text' &&
                            <a onClick={() => handleTextEvidence(row)} title='View Text'
                               className='btn btn-secondary btn-xs waves-effect waves-light mb-1'>
                                <i className='fe-type' style={{fontSize: '20px', color: 'white'}}/>
                            </a>
                        }
                        {
                            row.type === 'json' &&
                            <button
                                title='View Json'
                                className='btn btn-secondary btn-xs waves-effect waves-light mb-1'
                                onClick={() => handleJsonEvidence(row)}
                            >
                                <i className='fe-eye' style={{fontSize: '20px', color: 'white'}}/>
                            </button>
                        }
                    </Fragment>
                );
            },
        },
    ]

    const breadcumbsData = {
        "title": "Linked evidences",
        "breadcumbs": [
            {
                "title": "Compliance",
                "href": route('compliance-dashboard')
            },
            {
                "title": "Projects",
                "href": route('compliance-projects-view')
            },
            {
                "title": "Controls",
                "href": route('compliance-project-show', [project.id])
            },
            {
                "title": "Details",
                "href": route('compliance-project-control-show', [project.id, linkedToControlId, 'tasks'])
            },
            {
                "title": "Evidences",
                "href": "#"
            },
        ]
    };

    const TextEvidenceModal = ({showModal, onClose, heading = '', body = ''}) => {
        return (
            <Modal show={showModal} onHide={onClose} size={'xl'} centered>
                <Modal.Header className='px-3 pt-3 pb-0' closeButton>
                    <Modal.Title className='my-0'>{heading}</Modal.Title>
                </Modal.Header>
                <Modal.Body className='p-3'>{body}</Modal.Body>
                <Modal.Footer className='px-3 pt-0 pb-3'>
                    <button className="btn btn-secondary" onClick={onClose}>
                        Close
                    </button>
                </Modal.Footer>
            </Modal>
        );
    }

    const JsonEvidenceModal = ({showModal, onClose, body = ''}) => {
        return (
            <Modal show={showModal} onHide={onClose} size="lg" centered>
                <Modal.Header className='px-3 pt-3 pb-0' closeButton>
                    <Modal.Title className='my-0'>Json Evidence</Modal.Title>
                </Modal.Header>
                <Modal.Body>
                    <pre>
                        {body ? (
                            <div style={{background: '#f7f7f7', borderRadius: '6px', padding: '20px'}}>
                                <pre>{JSON.stringify(JSON.parse(body), undefined, 2)}</pre>
                            </div>
                        ) : null}
                    </pre>

                </Modal.Body>
                <Modal.Footer className='px-3 pt-0 pb-3'>
                    <button className="btn btn-secondary" onClick={onClose}>
                        Close
                    </button>
                </Modal.Footer>
            </Modal>
        );
    }

    return (
        <Fragment>
            <AppLayout>
                <BreadcumbsComponent data={breadcumbsData}/>
                <TextEvidenceModal
                    onClose={() => setTextEvidenceModalShow(false)}
                    showModal={textEvidenceModalShow}
                    body={textEvidenceText}
                    heading={textEvidenceHeading}
                />
                <
                    JsonEvidenceModal
                    onClose={() => setJsonEvidenceModalShow(false)}
                    showModal={jsonEvidenceModalShow}
                    body={jsonEvidence}
                />
                <div className="row">
                    <div className="col-xl-12">
                        <div className='card'>
                            <div className="card-body">
                                <Link
                                    href={props.previous_url}
                                    id="back_btn">
                                    <button type="button"
                                            className="btn btn-danger back-btn width-lg m-2 float-end">Back
                                    </button>
                                </Link>
                                <h4 className="header-title mb-4">Evidences</h4>
                                <DataTable
                                    columns={columns}
                                    fetchUrl={fetchURL}
                                    tag={`linked-control-evidences-${projectControlId}`}
                                    emptyString='No data found'
                                />
                            </div>
                        </div>
                    </div>
                    {/* <!-- end col --> */}
                </div>
                {/* <!-- end row --> */}
            </AppLayout>
        </Fragment>
    );
}

export default LinkedControlEvidences;
