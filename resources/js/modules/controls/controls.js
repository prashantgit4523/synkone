import React, { Fragment, useEffect, useState, useRef } from 'react';
import BreadcumbsComponent from '../../common/breadcumb/Breadcumb';
import './controls.scss';
import Select from '../../common/custom-react-select/CustomReactSelect';
import Spinner from 'react-bootstrap/Spinner';
import DataTable from '../../common/custom-datatable/AppDataTable';
import pdfjsWorker from "pdfjs-dist/build/pdf.worker.entry";
import { Link } from "@inertiajs/inertia-react";
import AppLayout from '../../layouts/app-layout/AppLayout';
import { useSelector, useDispatch } from "react-redux";
import { Accordion, Modal } from "react-bootstrap";
import fileDownload from "js-file-download";
import { parse as parseHeader } from 'content-disposition-header';
import { SizeMe } from "react-sizeme";
import { Document, Page, pdfjs } from "react-pdf";
import { Inertia } from '@inertiajs/inertia';
import { storeEvidenceData } from '../../store/actions/controls/evidenceData';
import { Button } from "react-bootstrap";

var defaultProductsData = [];
pdfjs.GlobalWorkerOptions.workerSrc = pdfjsWorker;
defaultProductsData.push({ value: 0, label: "Select Project" });

const EvidenceModal = ({ onClose, show, allEvidence }) => {
    //pdf view
    const dispatch = useDispatch();

    const textEvidencs = allEvidence.length ? allEvidence[0] : []
    const linkEvidenceFile = allEvidence.length ? allEvidence[1] : []
    const downloadEvidenceFile = allEvidence.length ? allEvidence[3] : []
    const documentUrlDoc = allEvidence.length ? allEvidence[4] : []
    const jsonEvidence = (allEvidence.length && allEvidence[5].length) ? allEvidence[5][0] : []
    const documentUrlAwareness = allEvidence.length ? allEvidence[6] : []
    const automationType = allEvidence.length ? allEvidence[7] : []
    const documentUrl = documentUrlDoc.length ? documentUrlDoc : documentUrlAwareness
    const additionalEvidenceTitle = (textEvidencs.length > 0 || linkEvidenceFile.length > 0 || downloadEvidenceFile.length > 0) ? true : false
    const isControlLinked = (jsonEvidence.length > 0 || documentUrl.length > 0) ? true : false
    const [singleEvidence, setSingleEvidence] = useState([]);

    useEffect(() => {
        dispatch(storeEvidenceData(allEvidence[3]))
    }, [allEvidence[3]]);
    const evidenceData = useSelector(state => state.controlReducer.evidenceDataReducer);

    let defaultItem
    if (documentUrl.length) defaultItem = 4
    else if (jsonEvidence.length) defaultItem = 5
    else if (textEvidencs.length) defaultItem = textEvidencs[0]['id']
    else if (linkEvidenceFile.length) defaultItem = 1
    else if (downloadEvidenceFile.length) defaultItem = 3

    const [numPages, setNumPages] = useState(null);
    const [pageNumber, setPageNumber] = useState(1);

    const [numPagesSingle, setNumPagesSingle] = useState(null);
    const [pageNumberSingle, setPageNumberSingle] = useState(1);

    const onDocumentLoadSuccess = ({ numPages }) => {
        setNumPages(numPages);
    }

    const onSingleDocumentLoadSuccess = ({ numPages }) => {
        setNumPagesSingle(numPages);
    }

    useEffect(() => {
        setNumPages(null);
        setPageNumber(1);
    }, [allEvidence[4], allEvidence[6]]);

    const downloadEvidence = async (url) => {
        try {
            dispatch({ type: "reportGenerateLoader/show", payload: "Downloading..." });
            let { data, headers } = await axiosFetch.get(url,
                {
                    responseType: "blob", // Important
                });
            let disposition = headers["content-disposition"];
            if (disposition && disposition.indexOf('attachment') !== -1) {
                if (disposition.includes('UTF')) { // if it's a zip file
                    // var filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/;
                    let filenameRegex = /filename\*?=([^']*'')?([^;]*)/;
                    let matches = filenameRegex.exec(disposition);
                    if (matches != null && matches[2]) {
                        let fileName = matches[2].replace(/['"]/g, '');
                        fileDownload(data, fileName);
                    }
                }
                else { // if it's a normal file
                    const header = parseHeader(headers['content-disposition']);
                    if (header.parameters?.filename) {
                        fileDownload(data, header.parameters.filename);
                    }
                }
                dispatch({ type: "reportGenerateLoader/hide" });
            } else {
                dispatch({ type: "reportGenerateLoader/hide" });
                AlertBox({
                    text: 'File Not Found',
                    confirmButtonColor: '#f1556c',
                    icon: 'error',
                });
            }
        } catch (error) {
            console.log(error);
        }
    };

    const getSingleDocumentData = (id) => {
        let singleEvidenceArray = evidenceData.evidenceData.filter(item => { return item.id == id })
        setSingleEvidence(singleEvidenceArray)
        setPageNumberSingle(1);
    }

    const downloadTxtFile = (title, last_response) => {
        const evidenceToDownload = JSON.stringify(JSON.parse(last_response), undefined, 2)
        const element = document.createElement('a');
        const file = new Blob([evidenceToDownload],
            { type: 'text/plain;charset=utf-8' });
        element.href = URL.createObjectURL(file);
        element.download = title + ' evidence.txt'
        document.body.appendChild(element);
        element.click();
    }

    return (
        <Modal show={show} onHide={onClose} centered className="modal-start">
            <Modal.Header closeButton></Modal.Header>
            <Modal.Body className={documentUrl.length ? "text-center" : ''} style={{ paddingTop: '10px' }}>

                <Accordion defaultActiveKey={defaultItem}>
                    <Modal.Title className="text-start"> Evidence</Modal.Title>
                    {documentUrl.length ? (
                        <>
                            <Accordion.Item eventKey={4} className='item-border-top'>
                                <Accordion.Header as="div">
                                    <div className="d-flex w-100 justify-content-between">
                                        <span className="d-inline-flex align-items-center fw-bold">
                                            <i className="fe-file-text icon-style" />
                                            <span className='display-name-style'>{documentUrl[1]}</span>
                                            <i className="fe-check icon-style" />
                                        </span>
                                    </div>
                                </Accordion.Header>
                                <Accordion.Body>
                                    <div className="overflow-hidden">
                                        <SizeMe>
                                            {({ size }) => (
                                                <Document file={documentUrl[0]} onLoadSuccess={onDocumentLoadSuccess}>
                                                    <Page pageNumber={pageNumber} height={450} width={size.width ? size.width : 1} />
                                                </Document>
                                            )}
                                        </SizeMe>

                                        <div className="d-flex px-2 align-items-center justify-content-between">
                                            {numPages && (
                                                <>
                                                    <a href={`${documentUrl[0]}&download=true`} className="btn btn-xs btn-secondary download-button" download>
                                                        <i className="mdi mdi-download-outline font-12 me-1" />
                                                        Download
                                                    </a>
                                                    <span>
                                                        Page {pageNumber} of {numPages}
                                                    </span>
                                                    <div className="btn-group" role="group" aria-label="pagination">
                                                        <button type="button" className="btn btn-xs btn-secondary" disabled={pageNumber === 1} onClick={() => setPageNumber(pageNumber - 1)}>Previous</button>
                                                        <button type="button" className="btn btn-xs btn-secondary" disabled={pageNumber === numPages} onClick={() => setPageNumber(pageNumber + 1)}>Next</button>
                                                    </div>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </Accordion.Body>
                            </Accordion.Item>
                        </>
                    ) : null}

                    {jsonEvidence.length ? (
                        <>
                            {jsonEvidence?.map(({ last_response, title, id, logo_link }) => (
                                <Accordion.Item key={id} eventKey={5} className='item-border-top'>
                                    <Accordion.Header as="div">
                                        <div className="d-flex w-100 justify-content-between">
                                            <span className="d-inline-flex align-items-center fw-bold">
                                                <img src={logo_link} width={'22px'} style={{ marginRight: '10px', borderRadius: '4px' }} />
                                                <span className='display-name-style'>{title}</span>
                                                <i className="fe-check icon-style" />
                                            </span>
                                        </div>
                                    </Accordion.Header>
                                    <Accordion.Body>
                                        <div id="evidence-form-section">
                                            <div className="json-evidence">
                                                <pre style={{ maxHeight: '350px' }}>{JSON.stringify(JSON.parse(last_response ?? '[{"message": "No configuration available"}]'), undefined, 2)}</pre>
                                            </div>
                                        </div>

                                        {last_response ? (
                                            <div class="d-flex justify-content-center">
                                                <div className='d-flex p-1 mt-1'>
                                                    <Button
                                                        title='Download Evidence'
                                                        className='btn btn-xs btn-secondary download-button'
                                                        onClick={
                                                            () => downloadTxtFile(title, last_response)
                                                        }
                                                    ><i className="mdi mdi-download-outline font-12 me-1" />Download</Button>
                                                </div>
                                            </div>
                                        )
                                            : null}
                                    </Accordion.Body>
                                </Accordion.Item>
                            ))}
                        </>
                    ) : null}

                    {(automationType[0] !== 'none' || (automationType[0] === 'none' && !isControlLinked)) && <>
                        {(additionalEvidenceTitle && automationType[0] !== 'none') && <>
                            <Modal.Title className="text-start">Additional Evidence</Modal.Title>
                        </>}

                        {textEvidencs?.map(({ name, id, text_evidence }) => (
                            <Accordion.Item eventKey={id} className='item-border-top'>
                                <Accordion.Header as="div">
                                    <div className="d-flex w-100 justify-content-between">
                                        <span className="d-inline-flex align-items-center fw-bold">
                                            <i className="fe-type icon-style" />
                                            <span className='display-name-style'>{name}</span>
                                            <i className="fe-check icon-style" />
                                        </span>
                                    </div>
                                </Accordion.Header>
                                <Accordion.Body>
                                    <div className="overflow-hidden">
                                        <span style={{ float: 'left' }}>{text_evidence}</span>
                                    </div>
                                </Accordion.Body>
                            </Accordion.Item>
                        ))}

                        {downloadEvidenceFile?.map(({ id, name, route, path, extention }) => (
                            <Accordion.Item eventKey={id} className={`item-border-top custom-icon-accordion-${(extention !== 'pdf' && extention !== 'png' && extention !== 'jpeg' && extention !== 'jpg') ? "disable" : ""}`}>
                                <Accordion.Header as="div" onClick={() => getSingleDocumentData(id)} >
                                    <div className="d-flex w-100 justify-content-between">
                                        <span className="d-inline-flex align-items-center fw-bold">
                                            <i className="fe-file-text icon-style" />
                                            <span className='display-name-style'>{name}</span>
                                            <i className="fe-check icon-style" />
                                        </span>
                                        {(extention != 'jpeg' && extention != 'jpg' && extention != 'png' && extention != 'pdf') &&
                                            <>
                                                <a className='btn btn-secondary btn-xs waves-effect waves-light download-button' title='Download All Document' style={{ float: 'right' }} onClick={() => downloadEvidence(route)}>
                                                    <i className='fe-download' style={{ fontSize: '12px', color: 'white', marginLeft: '2px' }}></i>
                                                </a>
                                            </>
                                        }
                                    </div>
                                </Accordion.Header>
                                {(extention == 'jpeg' || extention == 'jpg' || extention == 'png' || extention == 'pdf') &&
                                    <>
                                        <Accordion.Body>

                                            {(extention == 'pdf' && singleEvidence.length) &&
                                                <>
                                                    <div className="overflow-hidden">
                                                        <SizeMe>
                                                            {({ size }) => (
                                                                <Document file={`data:application/pdf;base64,${singleEvidence[0].path}`} onLoadSuccess={onSingleDocumentLoadSuccess}>
                                                                    <Page pageNumber={pageNumberSingle} height={450} width={size.width ? size.width : 1} />
                                                                </Document>
                                                            )}
                                                        </SizeMe>

                                                        <div className="d-flex px-2 align-items-center justify-content-between">
                                                            {numPagesSingle && (
                                                                <>
                                                                    <a className="btn btn-xs btn-secondary download-button" download onClick={() => downloadEvidence(route)}>
                                                                        <i className="mdi mdi-download-outline font-12 me-1" />
                                                                        Download
                                                                    </a>
                                                                    <span>
                                                                        Page {pageNumberSingle} of {numPagesSingle}
                                                                    </span>
                                                                    <div className="btn-group" role="group" aria-label="pagination">
                                                                        <button type="button" className="btn btn-xs btn-secondary" disabled={pageNumberSingle === 1} onClick={() => setPageNumberSingle(pageNumberSingle - 1)}>Previous</button>
                                                                        <button type="button" className="btn btn-xs btn-secondary" disabled={pageNumberSingle === numPagesSingle} onClick={() => setPageNumberSingle(pageNumberSingle + 1)}>Next</button>
                                                                    </div>
                                                                </>
                                                            )}
                                                        </div>
                                                    </div>
                                                </>
                                            }

                                            {(extention == 'jpeg' || extention == 'jpg' || extention == 'png') &&
                                                <>
                                                    <div className="overflow-hidden">
                                                        <img src={`data:image/png;base64,${path}`} width="100%" height={500} />
                                                        <a className='btn btn-secondary btn-xs waves-effect waves-light download-button' title='Download Document' style={{ marginTop: '10px', float: 'left' }} onClick={() => downloadEvidence(route)} >
                                                            <i className="mdi mdi-download-outline font-12 me-1" />
                                                            Download
                                                        </a>
                                                    </div>
                                                </>
                                            }
                                        </Accordion.Body>
                                    </>
                                }
                            </Accordion.Item>
                        ))}

                        {linkEvidenceFile.length ? (
                            <>
                                <Accordion.Item eventKey={1} className='item-border-top'>
                                    <Accordion.Header as="div">
                                        <div className="d-flex w-100 justify-content-between">
                                            <span className="d-inline-flex align-items-center fw-bold">
                                                <i className="fe-link icon-style" />
                                                <span className='display-name-style'>Link Evidence</span>
                                                <i className="fe-check icon-style" />
                                            </span>
                                        </div>
                                    </Accordion.Header>
                                    <Accordion.Body>
                                        {linkEvidenceFile?.map(({ name, id, path }) => (
                                            <>
                                                <div style={{ marginTop: '10px' }}>
                                                    <span style={{ float: 'left', marginRight: '8px' }}>{name}</span>
                                                    <i className="fe-check check-icon-style" />
                                                    <a href={path} className='btn btn-secondary btn-xs waves-effect waves-light' target="_blank" title='Click Link' style={{ marginRight: '2px', float: 'right' }}>
                                                        <i className='fe-link' style={{ fontSize: '12px', color: 'white' }}></i>
                                                    </a>
                                                </div>
                                                <div className='clearfix'></div>
                                            </>
                                        ))}
                                    </Accordion.Body>
                                </Accordion.Item>
                            </>
                        ) : null}
                    </>}
                </Accordion>
            </Modal.Body>
        </Modal>
    )
}

function Controls(props) {
    const dispatch = useDispatch();
    const [projects, setProjects] = useState({});
    const [controls, setControls] = useState('');
    const [controlIdValue, setControlID] = useState('');
    const [refresh, setRefresh] = useState(false);
    // const [taskContributors, setTaskContributor] = useState({});
    const projectSelectRef = useRef();
    const userSelectRef = useRef();

    const [ajaxData, setAjaxData] = useState({});
    const [allStandards, setAllStandards] = useState(null);

    // document automation
    const [evidenceModalShow, setEvidenceModalShow] = useState(false);
    const [allEvidence, setAllEvidence] = useState([]);

    useEffect(() => {
        document.title = "Implemented Controls";
        setProjects(defaultProductsData);
    }, []);

    useEffect(() => {
        return Inertia.on('before', e => {
            // check where user is going
            if (!((e.detail.visit.url.href).includes("/compliance/projects/") && (e.detail.visit.url.href).includes("/show"))) {
                localStorage.removeItem("controlPerPage");
                localStorage.removeItem("controlCurrentPage");
            }
        });
    });

    const appDataScope = useSelector(
        (state) => state.appDataScope.selectedDataScope.value
    );
    const fetchURL = "compliance/implemented-controls/data";

    const handleStandardChange = (e) => {
        setAjaxData(prevState => {
            return { ...prevState, project_id: "" }
        });
        getProjects(e.value);
        projectSelectRef.current.clearValue();
        setAjaxData(prevState => {
            return { ...prevState, standard_id: e.value ? e.value : "" }
        });
    };

    const handleProjectChange = (e) => {
        if (e != null) {
            setAjaxData(prevState => {
                return { ...prevState, project_id: e.value ? e.value : "" }
            });
        }
    };

    const handleUserChange = (e) => {
        if (e != null) {
            setAjaxData(prevState => {
                return { ...prevState, responsible_user: e.value ? e.value : "" }
            });
        }
    };

    const getProjects = (id) => {
        try {
            axiosFetch
                .get("compliance/tasks/get-projects-by-standards", {
                    params: {
                        standardId: id,
                        data_scope: appDataScope,
                    },
                })
                .then((res) => {
                    const response = res.data;
                    defaultProductsData = [];
                    defaultProductsData.push({
                        value: 0,
                        label: "Select Project",
                    });
                    response.map(function (each, index) {
                        defaultProductsData.push({
                            value: each.id,
                            label: each.name,
                        });
                    });
                    setProjects(defaultProductsData);
                });
        } catch (error) {
            console.log("Response error");
        }
    };

    const handleControlId = (e) => {
        setControlID(e.target.value);
        setAjaxData(prevState => {
            return { ...prevState, controlID: e.target.value ? e.target.value : "" }
        });
    };

    const handleControlName = (e) => {
        setControls(e.target.value);
        setAjaxData(prevState => {
            return { ...prevState, control_name: e.target.value ? e.target.value : "" }
        });
    };

    const resetValues = () => {
        setControlID("");
        setControls("");
        if (userSelectRef.current !== undefined) {
            userSelectRef.current.clearValue();
        }
    };

    useEffect(() => {
        // reset the values
        resetValues();
        getControlsData();
        setAjaxData({});
    }, [appDataScope]);

    const getControlEvidences = (id) => {
        try {
            dispatch({ type: "reportGenerateLoader/show", payload: "Loading Evidences..." });
            axiosFetch
                .get(route('compliance.implemented-controls.control.evidence'), {
                    params: {
                        project_control_id: id,
                        data_scope: appDataScope,
                    },
                })
                .then((res) => {
                    dispatch({ type: "reportGenerateLoader/hide" });
                    const response = res.data;
                    setAllEvidence(response.data)
                    setEvidenceModalShow(true);
                });
        } catch (error) {
            dispatch({ type: "reportGenerateLoader/hide" });
            console.log("Response error");
        }
    }

    useEffect(() => {
        setRefresh(!refresh);
    }, [ajaxData])

    const columns = [
        { accessor: '0', label: 'Standard', priority: 1, position: 1, minWidth: 160, sortable: true, as: 'standard.name' },
        { accessor: '1', label: 'Project', priority: 2, position: 2, minWidth: 100, sortable: true, as: 'project.name' },
        { accessor: '2', label: 'Control ID', priority: 3, position: 3, minWidth: 150, sortable: true, as: 'full_control_id' },
        {
            accessor: '3', label: 'Control Name', priority: 1, position: 4, minWidth: 180, sortable: true, as: 'name',
            CustomComponent: ({ row }) => {
                if (row[7]) {
                    return (
                        <Fragment>
                            <Link href={row[3].url}>
                                {decodeHTMLEntity(row[3].name)}
                            </Link>
                        </Fragment>
                    )
                }
            }
        },
        { accessor: '4', label: 'Control Description', priority: 1, position: 5, minWidth: 180, sortable: true, as: 'description', CustomComponent: ({ row }) => <span>{decodeHTMLEntity(row[4])}</span> },
        {
            accessor: '5', label: 'Automation', priority: 2, position: 6, minWidth: 100, sortable: true, as: 'automation',
            CustomComponent: ({ row }) => {
                var class_name = '';

                if (row[5] === 'technical') {
                    class_name = 'badge task-status-purple w-60';
                } else if (row[5] === 'document') {
                    class_name = 'badge task-status-purple w-60';
                } else if (row[5] === 'awareness') {
                    class_name = 'badge task-status-purple w-60';
                } else if (row[5] === 'none') {
                    class_name = 'badge task-status-red w-60';
                }

                return (
                    <Fragment>
                        <span style={{ textTransform: 'capitalize' }} className={class_name}>{row[5]}</span>
                    </Fragment>
                );
            },
        },
        { accessor: '6', label: 'Last Uploaded', priority: 1, position: 7, minWidth: 140, sortable: true, as: 'last_uploaded' },
        { accessor: '7', label: 'Responsible', priority: 2, position: 8, minWidth: 100, sortable: true, as: 'responsible' },
        {
            accessor: '8', label: 'Action', priority: 4, position: 9, minWidth: 80, sortable: false,
            CustomComponent: ({ row }) => {
                if (row[8]) {
                    return (
                        <Fragment>
                            <a className='btn btn-secondary btn-xs waves-effect waves-light' title='View Evidences' style={{ marginRight: '2px' }} onClick={() => getControlEvidences(row[9])}>
                                <i className='fe-eye' style={{ fontSize: '12px', color: 'white' }}></i>
                            </a>
                        </Fragment>
                    );
                }
                else {
                    return '';
                }
            },
        },
    ]

    const breadcumbsData = {
        title: "Controls",
        breadcumbs: [
            {
                title: "Controls",
                href: "/compliance/implemented-controls",
            },
        ],
    };

    const getControlsData = () => {
        setAllStandards(null);
        setProjects([{ value: 0, label: "Select Project" }]);
        if (projectSelectRef.current !== undefined) {
            projectSelectRef.current.clearValue();
        }

        axiosFetch
            .get(route("compliance.implemented-controls-data"), {
                params: {
                    data_scope: appDataScope,
                },
            })
            .then((res) => {
                const { allContributors, managedStandards } = res.data;
                setAllStandards(managedStandards);
            });
    };

    return (
        <Fragment>
            <EvidenceModal
                show={evidenceModalShow}
                allEvidence={allEvidence}
                onClose={() => {
                    setEvidenceModalShow(false);
                    setAllEvidence([]);
                }}
            />
            <AppLayout>
                <div id="implemented_controls_page">
                    <BreadcumbsComponent data={breadcumbsData} />
                    <div className="row">
                        <div className="col">
                            <div className="card">
                                <div className="card-body w-100">
                                    <div className="col-12  top-control mb-2">
                                        <div className="filter-row d-flex flex-column flex-sm-row justify-content-between my-2 p-2 rounded">
                                            <div className="filter-row__wrap d-flex flex-wrap">
                                                <div className="all-standards m-1">
                                                    {allStandards ? <Select className="react-select" classNamePrefix="react-select" defaultValue={allStandards[0]} options={allStandards} onChange={handleStandardChange} /> : <Spinner className="mt-2" animation="border" variant="dark" size="sm" />}
                                                </div>
                                                <div className="all-standards m-1">
                                                    {projects.length > 0 ? <Select className="react-select" classNamePrefix="react-select" ref={projectSelectRef} options={projects} isDisabled={projects.length == 1} onChange={handleProjectChange} /> : ""}
                                                </div>
                                                <div className="m-1 all-controlID"><input className="form-control filter-input" name="controlID" type="text" placeholder="Control ID" value={controlIdValue} onChange={handleControlId} /></div>
                                                <div className="m-1 all-controlName"><input className="form-control filter-input" name="control_name" type="text" placeholder="Control Name" value={controls} onChange={handleControlName} /></div>
                                                <div className="all-users m-1">
                                                    {props.taskContributors ? <Select className="react-select" classNamePrefix="react-select" ref={userSelectRef} options={props.taskContributors} onChange={handleUserChange} /> : <Spinner className="mt-2" animation="border" variant="dark" size="sm" />}
                                                </div>
                                            </div>

                                        </div>
                                        <div>
                                        </div>
                                    </div>
                                    <DataTable
                                        columns={columns}
                                        fetchUrl={fetchURL}
                                        data={ajaxData}
                                        refresh={refresh}
                                        tag="controls"
                                        emptyString='No data found'
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </AppLayout>
        </Fragment>
    );
}

export default Controls;
