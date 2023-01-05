import React, { useState, useEffect, useRef } from "react";

import { Inertia } from "@inertiajs/inertia";
import { useForm, usePage, Link } from "@inertiajs/inertia-react";
import { useSelector, useDispatch } from "react-redux";
import { Button } from "react-bootstrap";
import Select from "../../../../../common/custom-react-select/CustomReactSelect";
import Swal from "sweetalert2";
import moment from "moment/moment";
import axios from 'axios';
import { Nav, Tab, Alert, Accordion, OverlayTrigger, Tooltip } from "react-bootstrap";

import Flatpickr from "react-flatpickr";
import CustomDropify from "../../../../../common/custom-dropify/CustomDropify";
import fileDownload from "js-file-download";
import { parse as parseHeader } from 'content-disposition-header';
import LoadingButton from '../../../../../common/loading-button/LoadingButton';
import { SizeMe } from 'react-sizeme';
import { Document, Page, pdfjs } from 'react-pdf';

import { showToastMessage } from "../../../../../utils/toast-message";
import AddAwarenessCampaignForm from "../components/AddAwarenessCampaignForm"
import RejectAmendmentModal from "../components/RejectAmendmentModal";
import RequestAmendmentModal from "../components/RequestAmendmentModal";
import TextEvidenceModal from "../components/TextEvidenceModal";
import ControlsModal from "../components/ControlsModal";
import RejectModal from "../components/RejectModal";
import EvidenceItem from "../components/EvidenceItem";
import CommentItem from "../components/CommentItem";

import pdfjsWorker from "pdfjs-dist/build/pdf.worker.entry";

import 'rc-switch/assets/index.css';
import 'flatpickr/dist/themes/light.css';

pdfjs.GlobalWorkerOptions.workerSrc = pdfjsWorker;


export const diffForHumans = date => moment(date).fromNow();

const allowedRoles = [
    "Global Admin",
    "Compliance Administrator",
    "Contributor",
];

const TasksTab = (props) => {
    const appDataScope = useSelector(state => state.appDataScope.selectedDataScope.value);
    const commentsBoxRef = useRef(null);
    const [activeTab, setActiveTab] = useState(null);
    const [buttonValue, setButtonValue] = useState("Save");
    const dispatch = useDispatch();
    const { active, campaignTypeFilter, searchQuery, policies, groups, groupUsers, controlId, ssoIsEnabled, manualOverrideResponsibleRequired } = props;
    const [showModal, setShowModal] = useState(false);
    const [isReviewSubmitting, setIsReviewSubmitting] = useState(false);
    const [iseApproving, setIsApproving] = useState(false);
    const [requestAmendModalShow, setRequestAmendModalShow] = useState(false);
    const [rejectAmendModalShow, setRejectAmendModalShow] = useState(false);
    const [acceptingAmendment, setAcceptingAmendment] = useState(false);
    const [updatingAutomation, setUpdatingAutomation] = useState(false);
    const [contributors, setContributors] = useState([]);

    const [selectedRow, setSelectedRow] = useState(null);
    const [selectedRowUpdated, setSelectedRowUpdated] = useState(false);
    //text evidence modal
    const [textEvidenceModalShow, setTextEvidenceModalShow] = useState(false);
    const [textEvidenceHeading, setTextEvidenceHeading] = useState("");
    const [textEvidenceText, setTextEvidenceText] = useState("");
    //reject modal
    const [rejectModalShow, setRejectModalShow] = useState(false);
    //pdf view
    const [numPages, setNumPages] = useState(null);
    const [pageNumber, setPageNumber] = useState(1);
    const [campaignDataId, setCampaignDataId] = useState(null);
    const [campaignOwnerId, setCampaignOwnerId] = useState();
    const [campaignOwnerName, setCampaignOwnerName] = useState();
    const [campaignDataLoaded, setCampaignDataLoaded] = useState(false);
    const [campaignOwnerDepartmentName, setcampaignOwnerDepartmentName] = useState();

    const [mergedEvidences, setMergedEvidences] = useState([]);

    const [documentName, setDocumentName] = useState(false);
    const [documentEvidence, setDocumentEvidence] = useState(false);
    const [createName, setCreateName] = useState(false);
    const [createLink, setCreateLink] = useState(false);
    const [inputName, setInputName] = useState(false);
    const [inputText, setInputText] = useState(false);
    const {
        globalSetting,
        projectControl,
        hasLinkedEvidence,
        linkedEvidencesControl,
        comments,
        meta,
        authUser,
        authUserRoles,
        latestJustification,
        project,
        justificationStatus,
        frequencies,
        integrations,
        hasPolicyRole,
        hasComplianceRole,
    } = usePage().props;

    const addCampaignFormRef = useRef(null);
    const [isFormSubmitting, setIsFormSubmitting] = useState(false);

    const controlStatus = projectControl.status;

    const commentForm = useForm({
        comment: "",
    });

    const evidencesForm = useForm({
        project_control_id: projectControl.id,
        name2: "",
        evidences: null,
        name: "",
        link: "",
        linked_to_project_control_id: "",
        active_tab: "upload-docs",
        text_evidence_name: "",
        text_evidence: "",
    });

    const onDocumentLoadSuccess = ({ numPages }) => {
        setNumPages(numPages);
    }

    const handleRowSelected = (row) => {
        evidencesForm.setData(
            "linked_to_project_control_id",
            row.project_control_id
        );
        setSelectedRow(row);
        // hide the modal
        setShowModal(false);
        setSelectedRowUpdated(true);
    };

    const handleOnSubmitComment = (e) => {
        e.preventDefault();
        commentForm.post(
            route("compliance.project-controls-comments", [
                project.id,
                projectControl.id,
            ]),
            {
                preserveScroll: true,
                onSuccess: () => {
                    Inertia.reload({ only: ["comments"] });
                    commentForm.reset("comment");
                },
            }
        );
    };

    useEffect(() => {
        axiosFetch.get(route('common.contributors'), {
            params: {
                editable: projectControl.is_editable,
                force_fetch: true
            }
        })
            .then(({ data }) => {
                const c = Object.keys(data).map(k => ({ label: k, value: data[k] }));
                setContributors(c);
            });
        fetchMergedEvidences();
    }, []);

    useEffect(() => {
        // always scroll down when tab active
        if (active && projectControl.automation === 'none' && (comments.length > 0 || authUser.id === projectControl.responsible || authUser.id === projectControl.approver))
            commentsBoxRef.current.scrollTop = commentsBoxRef.current.scrollHeight;
    }, [comments, active]);

    useEffect(() => {
        setExistingControlRow();
    }, [projectControl]);

    useEffect(() => {
        // change button value when navigating tabs
        if (activeTab !== null) {
            evidencesForm.setData("active_tab", activeTab);
            if (activeTab === "upload-docs") return setButtonValue("Upload");
            setButtonValue("Save");
        }
        setExistingControlRow();
    }, [activeTab]);

    const setExistingControlRow = () => {
        if (hasLinkedEvidence && linkedEvidencesControl) {
            let data = {
                standard: linkedEvidencesControl.project.standard,
                project_name: linkedEvidencesControl.project.name,
                control_name: linkedEvidencesControl.name
            };
            setSelectedRow(data);
            evidencesForm.setData("active_tab", 'existing-control');
        } else {
            let data = {
                standard: '',
                project_name: '',
                control_name: ''
            };
            setSelectedRow(data);
        }
    }

    const fetchMergedEvidences = () => {
        axios.get(route('compliance.project.control.merged-evidences', [project.id, projectControl.id]), { params: { data_scope: projectControl.self_data_scope } }).then(({ data }) => {
            setMergedEvidences(data);
        });
    }

    const fetchCampaignDataId = () => {
        axios.get(route('compliance.project.control.campaign-data-id')).then(({ data }) => {
            if (data.success && data.data) {
                setCampaignDataId(data.data.id);
                setCampaignOwnerId(data.data.owner_id);
                setCampaignOwnerName(data.data.owner_name);
                setcampaignOwnerDepartmentName(data.data.owner_department_name);
            }
            setCampaignDataLoaded(data.success);
        });
    }

    useEffect(() => {
        fetchCampaignDataId();
        return Inertia.on('finish', () => {
            fetchMergedEvidences();
        });
    }, []);

    const handleOnEvidencesSubmit = (e) => {
        e.preventDefault();

        if (evidencesForm.data.active_tab == 'upload-docs')
            dispatch({ type: "reportGenerateLoader/show", payload: "Uploading..." });

        evidencesForm.post(
            route("compliance-project-control-evidences-upload", [
                project.id,
                projectControl.id,
            ]),
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    Inertia.reload({ only: ["projectControl"] });
                    evidencesForm.reset(
                        "name2",
                        "evidences",
                        "name",
                        "link",
                        "linked_to_project_control_id",
                        "text_evidence_name",
                        "text_evidence"
                    );
                    if (evidencesForm.data.linked_to_project_control_id) {
                        showToastMessage('Control linked successfully!', 'success');
                    }

                    if (evidencesForm.data.active_tab == 'upload-docs')
                        dispatch({ type: "reportGenerateLoader/hide" });

                    setSelectedRowUpdated(false);
                    // reset selectedRow
                    // setSelectedRow(null);
                },
                onError: (res) => {
                    if (evidencesForm.data.active_tab == 'upload-docs')
                        dispatch({ type: "reportGenerateLoader/hide" });
                },
                onFinish: () => {
                    if (evidencesForm.data.active_tab == 'upload-docs')
                        dispatch({ type: "reportGenerateLoader/hide" });
                }
            }
        );
        setDocumentEvidence(false);
        setDocumentName(false);
        setCreateName(false);
        setCreateLink(false);
        setInputName(false);
        setInputText(false);
    };

    const handleOnAdditionalEvidencesSubmit = (e) => {
        e.preventDefault();
        evidencesForm.post(
            route("compliance-project-control-additional-evidences-upload", [
                project.id,
                projectControl.id,
            ]),
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    Inertia.reload({ only: ["projectControl"] });
                    evidencesForm.reset(
                        "name2",
                        "evidences",
                        "name",
                        "link",
                        "linked_to_project_control_id",
                        "text_evidence_name",
                        "text_evidence"
                    );
                    // reset selectedRow
                    setSelectedRow(null);
                },
            }
        );
    };

    const handleOnSubmitReview = (e) => {
        e.preventDefault();
        Swal.fire({
            title: "Confirm submission?",
            text: "Review your evidence before submitting.",
            icon: 'question',
            iconColor: '#b2dd4c',
            showCancelButton: true,
            confirmButtonColor: "#b2dd4c",
            confirmButtonText: "Submit",
        }).then((confirmed) => {
            if (confirmed.value) {
                Inertia.post(
                    route("compliance.project-controls-review-submit", [
                        project.id,
                        projectControl.id,
                    ]),
                    null,
                    {
                        onSuccess: (page) => {
                            if (!page.props.flash.error) {
                                Swal.fire({
                                    title: "Submitted!",
                                    text: "Your evidence was submitted successfully.",
                                    confirmButtonColor: "#b2dd4c",
                                    icon: 'success',
                                });
                                Inertia.reload();
                            }
                        },
                        onStart: () => setIsReviewSubmitting(true),
                    }
                );
            }
        });
        //
    };

    const handleApproveEvidence = () => {
        Swal.fire({
            title: "Approve Evidence Confirmation",
            text: "Are you sure?",
            showCancelButton: true,
            confirmButtonColor: "#b2dd4c",
            confirmButtonText: "Approve",
            icon: 'question',
            iconColor: '#b2dd4c',
        }).then((confirmed) => {
            if (confirmed.value) {
                Inertia.post(
                    route("compliance.project-controls-review-approve", [
                        project.id,
                        projectControl.id,
                    ]),
                    null,
                    {
                        onStart: () => setIsApproving(true),
                        onFinish: () => setIsApproving(false),
                        onSuccess: () => {
                            Swal.fire({
                                title: "Success!",
                                text: "The evidence was approved successfully.",
                                confirmButtonColor: "#b2dd4c",
                                icon: 'success'
                            });
                            Inertia.reload();
                        },
                    }
                );
            }
        });
    };

    const defaultActiveTab = hasLinkedEvidence ? "existing-control"
        : globalSetting.allow_document_upload
            ? "upload-docs"
            : globalSetting.allow_document_link
                ? "create-link"
                : "existing-control";

    const handleExistingControlClick = () => {
        setShowModal(true);
        // setSelectedRow(null);
        evidencesForm.setData("linked_to_project_control_id", null);
    };

    const downloadEvidence = async (url) => {
        try {
            dispatch({ type: "reportGenerateLoader/show", payload: "Downloading..." });
            let { data, headers } = await axiosFetch.get(url,
                {
                    responseType: "blob", // Important
                });
            if (headers["content-disposition"]) {
                const header = parseHeader(headers['content-disposition']);
                if (header.parameters?.filename) {
                    fileDownload(data, header.parameters.filename);
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
        }
    };

    const handleEvidenceAction = (type, { url, name, text }) => {
        switch (type) {
            case "json":
            case "text":
                setTextEvidenceHeading(name);
                setTextEvidenceText(text);
                setTextEvidenceModalShow(true);
                break;
            case 'document':
                downloadEvidence(url);
                break;
            case 'control':
                Inertia.visit(url)
                break;
            case 'awareness':
                downloadEvidence(url);
                break;
            default:
                window.open(url, '_blank');
        }
    };

    const handleAcceptAmendment = () => {
        Inertia.post(route("compliance.project-controls-amend-request-decision", [project.id, projectControl.id]), {
            solution: 'accepted',
            data_scope: appDataScope
        }, {
            onStart: () => setAcceptingAmendment(true),
            onFinish: () => {
                Inertia.reload();
                setAcceptingAmendment(false);
            }
        })
    }

    const disableAutomation = () => {
        let selectedResponsible = null;
        let selectedContributor = null;
        let selectedFrequency = null;
        let selectedDeadline = moment().format("YYYY-MM-DD");
        AlertBox({
            title: "Are you sure?",
            text: "Control Automation will be disabled. You'll have to manually upload the evidence.",
            showCancelButton: true,
            confirmButtonColor: "#ff0000",
            confirmButtonText: "Yes, disable it!",
            icon: 'warning',
            iconColor: '#ff0000',
            html: (
                <div>
                    <span style={{ fontSize: '1.125em' }}>Control Automation will be disabled. You'll have to manually upload the evidence.</span>
                    <div style={{ padding: "4px", marginTop: '10px', fontSize: '16px' }}>
                        {manualOverrideResponsibleRequired &&
                            <div className="mb-1">
                                <Select
                                    placeholder={`Select responsible...`}
                                    onChange={o => {
                                        selectedResponsible = o;
                                    }}
                                    menuPortalTarget={document.body}
                                    styles={{
                                        menuPortal: (base) => ({
                                            ...base,
                                            zIndex: 9999,
                                        }),
                                    }}
                                    options={contributors.filter(({ value }) => value !== projectControl?.responsible)}
                                />
                            </div>
                        }
                        <Select
                            placeholder={`Select approver...`}
                            onChange={o => {
                                selectedContributor = o;
                            }}
                            menuPortalTarget={document.body}
                            styles={{
                                menuPortal: (base) => ({
                                    ...base,
                                    zIndex: 9999,
                                }),
                            }}
                            options={contributors.filter(({ value }) => value !== projectControl?.responsible)}
                        />

                        <Select
                            placeholder={'Select a frequency...'}
                            menuPortalTarget={document.body}
                            className="mt-1 mb-1"
                            styles={{
                                menuPortal: (base) => ({
                                    ...base,
                                    zIndex: 9999,
                                }),
                            }}
                            options={frequencies.map((f) => ({
                                value: f,
                                label: f,
                            }))}
                            onChange={o => {
                                selectedFrequency = o;
                            }}
                        />
                        <div className="input-group">
                            <Flatpickr
                                placeholder={'Select a deadline...'}
                                className="form-control text-center"
                                options={{
                                    altFormat: 'd-m-Y',
                                    dateFormat: 'Y-m-d',
                                    minDate: 'today',
                                    altInput: true,
                                    defaultDate: new Date()
                                }}
                                onChange={(_, date) => {
                                    selectedDeadline = date;
                                }}
                                style={{ fontSize: '1.01em' }}
                            />
                            <div className="border-start-0">
                                <span className="input-group-text bg-none" style={{ lineHeight: '1.8' }}>
                                    <i className="mdi mdi-calendar-outline" />
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            ),
            imageWidth: 120,
            preConfirm: () => {
                setUpdatingAutomation(true)
                return axiosFetch.post(route("update-project-control-automation", [project.id, projectControl.id]), {
                    automation: 'none',
                    approver: selectedContributor?.value,
                    responsible: manualOverrideResponsibleRequired ? selectedResponsible?.value : projectControl.responsible,
                    frequency: selectedFrequency?.value,
                    deadline: selectedDeadline,
                    data_scope: appDataScope,
                    manualOverride: 'none'
                })
                    .then(() => Inertia.reload({ preserveState: false }))
                    .catch(({ response: { data: { errors } } }) => {
                        Object.keys(errors).forEach(k => Swal.showValidationMessage(errors[k][0]));
                    })
                    .finally(() => setUpdatingAutomation(false));

            }
        }, function (e) {
        });
    }

    const enableAutomation = () => {
        AlertBox({
            title: "Are you sure?",
            text: "Control Automation will be enabled.",
            showCancelButton: true,
            confirmButtonColor: "#ff0000",
            confirmButtonText: "Yes, enable it!",
            icon: 'warning',
            iconColor: '#ff0000'
        },
            function (confirmed) {
                if (confirmed.value) {
                    Inertia.post(route("update-project-control-automation", [project.id, projectControl.id]), {
                        data_scope: appDataScope
                    }, {
                        onStart: () => setUpdatingAutomation(true),
                        onFinish: () => setUpdatingAutomation(false)
                    })
                }
            })
    }

    useEffect(() => {
        if (documentName || documentEvidence || createName || createLink || inputName || inputText) {
            const removeBeforeEventListener = Inertia.on('before', e => {
                // user is going somewhere, allow save & restore
                if (![route('compliance-project-control-evidences-upload', [project.id, projectControl.id]), route('compliance-project-control-show', [project.id, projectControl.id])].includes(e.detail.visit.url.href)) {
                    e.preventDefault();
                    AlertBox({
                        title: 'Are you sure?',
                        text: 'You didn\'t save your evidence.',
                        confirmButtonColor: '#6c757d',
                        cancelButtonColor: '#f1556c',
                        allowOutsideClick: false,
                        icon: 'warning',
                        iconColor: '#f1556c',
                        showCancelButton: true,
                        confirmButtonText: 'Cancel',
                        cancelButtonText: 'Leave'
                    }, function (result) {
                        if (!result.isConfirmed) {
                            removeBeforeEventListener();
                            Inertia.get(e.detail.visit.url.href);
                        }
                    })
                }
            });

            return removeBeforeEventListener;
        }
    }, [documentName, documentEvidence, createName, createLink, inputName, inputText]);

    const checkUnsavedValue = (e) => (e.target.value !== '') ? setDocumentName(true) : setDocumentName(false)
    const checkUploadFile = (file) => (file) ? setDocumentEvidence(true) : setDocumentEvidence(false)
    const checkCreateNameValue = (e) => (e.target.value !== '') ? setCreateName(true) : setCreateName(false)
    const checkCreateLinkValue = (e) => (e.target.value !== '') ? setCreateLink(true) : setCreateLink(false)
    const checkInputNameValue = (e) => (e.target.value !== '') ? setInputName(true) : setInputName(false)
    const checkInputTextValue = (e) => (e.target.value !== '') ? setInputText(true) : setInputText(false)

    const downloadTxtFile = (name, action_name, last_response) => {
        const evidenceToDownload = JSON.stringify(JSON.parse(last_response), undefined, 2)
        const element = document.createElement('a');
        const file = new Blob([evidenceToDownload],
            { type: 'text/plain;charset=utf-8' });
        element.href = URL.createObjectURL(file);
        element.download = action_name + ' on ' + name + ' evidence.txt'
        document.body.appendChild(element);
        element.click();
    }

    return (
        <div className="tab-padding">
            <div className="row">
                <div className="col-xl-12">
                    {
                        authUser.id !== projectControl.responsible &&
                            authUser.id !== projectControl.approver ? (
                            <Alert variant={"info d-flex align-items-center justify-content-between"}>
                                <span>
                                    <i className="fas fa-exclamation-circle flex-shrink-0 me-1" />
                                    <span>You are not responsible for this control.</span>
                                </span>
                            </Alert>
                        ) : null
                    }
                </div>

                <div className="col-xl-6">
                    <RejectAmendmentModal
                        show={rejectAmendModalShow}
                        onClose={() => setRejectAmendModalShow(false)}
                    />
                    <RequestAmendmentModal
                        show={requestAmendModalShow}
                        onClose={() => setRequestAmendModalShow(false)}
                    />
                    <TextEvidenceModal
                        onClose={() => setTextEvidenceModalShow(false)}
                        showModal={textEvidenceModalShow}
                        body={textEvidenceText}
                        heading={textEvidenceHeading}
                    />
                    <ControlsModal
                        showModal={showModal}
                        onClose={() => setShowModal(false)}
                        onSelectRow={handleRowSelected}
                    />
                    <RejectModal
                        showModal={rejectModalShow}
                        onClose={() => setRejectModalShow(false)}
                    />

                    {
                        projectControl.automation === 'none' &&
                            projectControl.standardControlAutomation &&
                            projectControl.standardControlAutomation !== 'none' &&
                            projectControl.responsible === authUser.id ? (
                            <Alert variant="secondary" className="mb-4">
                                <Alert.Heading>This control
                                    supports {projectControl.standardControlAutomation} automation.</Alert.Heading>
                                <p>Turn on automation on this control to automate and avoid the hassle of manually
                                    uploading
                                    the evidence.</p>
                                <button
                                    onClick={enableAutomation}
                                    type="submit"
                                    className="btn btn-primary"
                                    id="evidence-submit"
                                    disabled={updatingAutomation}
                                >
                                    Enable Automation
                                </button>
                            </Alert>
                        ) : null
                    }

                    {
                        projectControl.automation === 'document' &&
                            mergedEvidences.length > 0 &&
                            mergedEvidences[0].status === 'draft' &&
                            projectControl.responsible === authUser.id &&
                            !mergedEvidences[0].is_generated ? (
                            <Alert variant="warning d-flex align-items-center justify-content-between">
                                <span>
                                    <i className="fas fa-exclamation-triangle flex-shrink-0 me-1" />
                                    <span>To implement this control you need to publish the document.</span>
                                </span>
                                <Link
                                    href={route('documents.show', {
                                        document: projectControl.document_template_id,
                                        control: projectControl.id
                                    })}
                                    className={'btn btn-xs btn-primary'}
                                    style={{ float: 'right' }}
                                >
                                    Click Here
                                </Link>
                            </Alert>
                        ) : null
                    }

                    {
                        projectControl.automation === 'document' &&
                            projectControl.status === 'Not Implemented' &&
                            mergedEvidences[0] &&
                            mergedEvidences[0].meta &&
                            mergedEvidences[0].is_generated ? (
                            <Alert variant="warning">
                                <p>
                                    <i className="fas fa-exclamation-triangle" />&nbsp;To implement this
                                    control, {mergedEvidences[0].meta.suffix}
                                </p>
                                <div className="d-flex flex-row-reverse">
                                    <Link
                                        href={mergedEvidences[0].meta.route}
                                        className="btn btn-xs btn-primary float-end"
                                    >
                                        Click Here
                                    </Link>
                                </div>
                            </Alert>
                        ) : null
                    }

                    {
                        (authUser.id === projectControl.responsible || authUser.id === projectControl.approver) ? (
                            <>
                                {
                                    projectControl.automation !== 'none' ? (
                                        <button
                                            onClick={disableAutomation}
                                            type="submit"
                                            className="btn btn-primary mb-2"
                                            id="evidence-submit"
                                            disabled={updatingAutomation}
                                        >
                                            Override / Manual Upload
                                        </button>
                                    ) : null
                                }

                                {
                                    (
                                        (projectControl.automation === 'awareness' && authUser.id === campaignOwnerId) ||
                                        (projectControl.automation === "document" && projectControl.status === 'Implemented') ||
                                        (projectControl.automation === "technical")
                                    ) ? (
                                        <div className="pb-5 mb-3">
                                            <div className="custom-icon-accordion mt-2 mb-2">
                                                <Accordion>
                                                    <Accordion.Item key="0" eventKey="0">
                                                        <Accordion.Header as="div">
                                                            <div className="d-flex w-100 justify-content-between">
                                                                <span className="d-inline-flex align-items-center fw-bold">
                                                                    <i className="fe-file-text" style={{ fontSize: '18px' }} />
                                                                    <span
                                                                        style={{ marginLeft: '5px' }}>Upload Additional Evidence</span>
                                                                </span>
                                                            </div>
                                                        </Accordion.Header>
                                                        <Accordion.Body style={{ marginBottom: '40px' }}>
                                                            <form
                                                                method="POST"
                                                                id="additional-evidence-upload-form"
                                                                encType="multipart/form-data"
                                                                onSubmit={handleOnAdditionalEvidencesSubmit}
                                                            >
                                                                <div className="row mb-3">
                                                                    <label
                                                                        className="col-xl-2 col-lg-2 col-md-2 form-label col-form-label"
                                                                        htmlFor="name2"
                                                                    >
                                                                        Name:
                                                                        <span className="required text-danger">*</span>
                                                                    </label>
                                                                    <div className="col-xl-10 col-lg-10 col-md-10">
                                                                        <input
                                                                            type="text"
                                                                            name="name2"
                                                                            className="form-control"
                                                                            id="name2"
                                                                            value={evidencesForm.data.name2}
                                                                            onChange={(e) => evidencesForm.setData("name2", e.target.value)}
                                                                        />
                                                                        {evidencesForm.errors.name2 ? <div
                                                                            className="invalid-feedback d-block">{evidencesForm.errors.name2}</div> : null}
                                                                    </div>
                                                                </div>
                                                                <div
                                                                    className="row mb-3"
                                                                    id="evidence-section"
                                                                >
                                                                    <label
                                                                        className="col-xl-2 col-lg-2 col-md-2 col-form-label"
                                                                        htmlFor="evidences"
                                                                    >
                                                                        Evidence:{" "}
                                                                        <span className="required text-danger">*</span>
                                                                    </label>
                                                                    <div className="col-xl-10 col-lg-10 col-md-10">
                                                                        <CustomDropify
                                                                            maxSize={15728640}
                                                                            file={evidencesForm.data.evidences}
                                                                            onSelect={file => evidencesForm.setData(previousData => ({
                                                                                ...previousData,
                                                                                evidences: file
                                                                            }))}
                                                                            accept={'.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.png,.jpeg,.gif,.pdf,.msg,.eml'}
                                                                        />

                                                                        {evidencesForm.errors.evidences ? <div
                                                                            className="invalid-feedback d-block">{evidencesForm.errors.evidences}</div> : null}
                                                                        <div className="file-validation-limit mt-3">
                                                                            <div>
                                                                                <p>
                                                                                    <span
                                                                                        className="me-1">Accepted File Types: </span>
                                                                                    .doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.png,.jpeg,.gif,.pdf,.msg,.eml
                                                                                </p>
                                                                            </div>
                                                                            <p>
                                                                                <span className="me-1">
                                                                                    Maximum File
                                                                                    Size:{" "}
                                                                                </span>
                                                                                15MB
                                                                            </p>
                                                                            <p>
                                                                                <span className="me-1">
                                                                                    Maximum
                                                                                    Character
                                                                                    Length:{" "}
                                                                                </span>
                                                                                250
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <button
                                                                    type="submit"
                                                                    className="btn btn-primary float-end"
                                                                    id="evidence-submit"
                                                                    disabled={evidencesForm.processing}
                                                                >
                                                                    Upload Additional Evidence
                                                                </button>
                                                            </form>
                                                        </Accordion.Body>
                                                    </Accordion.Item>
                                                </Accordion>
                                            </div>
                                        </div>
                                    ) : null
                                }
                            </>
                        ) : null
                    }

                    {
                        authUserRoles.some((role) => allowedRoles.includes(role)) &&
                            authUser.id === projectControl.responsible &&
                            projectControl.automation === 'none' &&
                            meta.evidence_upload_allowed ? (
                            <div className="custom-icon-accordion mt-2 mb-2">
                                <Accordion defaultActiveKey={['0']} alwaysOpen>
                                    <Accordion.Item key="0" eventKey="0">
                                        <Accordion.Header as="div">
                                            <div
                                                className="upload-additional-evidence d-flex w-100 justify-content-between">
                                                <span className="d-inline-flex align-items-center fw-bold">
                                                    <i className="fe-file" style={{ fontSize: '18px' }} />
                                                    <span style={{ marginLeft: '5px' }}>Upload Evidence</span>
                                                </span>
                                            </div>
                                        </Accordion.Header>
                                        <Accordion.Body>
                                            <div id="evidence-form-section" className="pb-5 mb-3">
                                                <form
                                                    method="POST"
                                                    id="evidence-upload-form"
                                                    encType="multipart/form-data"
                                                    onSubmit={handleOnEvidencesSubmit}
                                                >
                                                    <Tab.Container
                                                        onSelect={(eventKey) => setActiveTab(eventKey)}
                                                        defaultActiveKey={defaultActiveTab}
                                                    >
                                                        <Nav variant="pills" className="flex-row">
                                                            {globalSetting.allow_document_upload ? (
                                                                <Nav.Item>
                                                                    <Nav.Link
                                                                        className={`btn bg-secondary text-white me-2 ${hasLinkedEvidence ? 'disabled' : ''}`}
                                                                        eventKey="upload-docs"
                                                                    >
                                                                        Upload Document
                                                                    </Nav.Link>
                                                                </Nav.Item>
                                                            ) : null}

                                                            {globalSetting.allow_document_link ? (
                                                                <Nav.Item>
                                                                    <Nav.Link
                                                                        className={`btn bg-secondary text-white me-2 ${hasLinkedEvidence ? 'disabled' : ''}`}
                                                                        eventKey="create-link"
                                                                    >
                                                                        Create Link
                                                                    </Nav.Link>
                                                                </Nav.Item>
                                                            ) : null}

                                                            <Nav.Item>
                                                                <Nav.Link
                                                                    className="btn bg-secondary text-white me-2"
                                                                    onClick={
                                                                        !hasLinkedEvidence && handleExistingControlClick
                                                                    }
                                                                    eventKey="existing-control"
                                                                >
                                                                    Existing Control
                                                                </Nav.Link>
                                                            </Nav.Item>

                                                            <Nav.Item>
                                                                <Nav.Link
                                                                    className={`btn bg-secondary text-white ${hasLinkedEvidence ? 'disabled' : ''}`}
                                                                    eventKey="text-input"
                                                                >
                                                                    Text Input
                                                                </Nav.Link>
                                                            </Nav.Item>
                                                        </Nav>
                                                        <Tab.Content className="mt-2">
                                                            {globalSetting.allow_document_upload ? (
                                                                <Tab.Pane eventKey="upload-docs">
                                                                    <div className="row mb-3">
                                                                        <label
                                                                            className="col-xl-2 col-lg-2 col-md-2 form-label col-form-label"
                                                                            htmlFor="name2"
                                                                        >
                                                                            Name:
                                                                            <span
                                                                                className="required text-danger">*</span>
                                                                        </label>
                                                                        <div className="col-xl-10 col-lg-10 col-md-10">
                                                                            <input
                                                                                type="text"
                                                                                name="name2"
                                                                                className="form-control"
                                                                                id="name2"
                                                                                value={evidencesForm.data.name2}
                                                                                onChange={(e) => {
                                                                                    checkUnsavedValue(e);
                                                                                    evidencesForm.setData("name2", e.target.value);
                                                                                }}
                                                                            />
                                                                            {evidencesForm.errors.name2 && (
                                                                                <div
                                                                                    className="invalid-feedback d-block"
                                                                                >
                                                                                    {evidencesForm.errors.name2}
                                                                                </div>
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                    <div
                                                                        className="row mb-3"
                                                                        id="evidence-section"
                                                                    >
                                                                        <label
                                                                            className="col-xl-2 col-lg-2 col-md-2 col-form-label"
                                                                            htmlFor="evidences"
                                                                        >
                                                                            Evidence:
                                                                            <span
                                                                                className="required text-danger">*</span>
                                                                        </label>
                                                                        <div className="col-xl-10 col-lg-10 col-md-10">
                                                                            <CustomDropify
                                                                                maxSize={15000000}
                                                                                file={evidencesForm.data.evidences}
                                                                                onSelect={file => {
                                                                                    checkUploadFile(file);
                                                                                    evidencesForm.setData(previousData => ({
                                                                                        ...previousData,
                                                                                        evidences: file
                                                                                    }));
                                                                                }
                                                                                }
                                                                                accept={'.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.png,.jpeg,.gif,.pdf,.msg,.eml'}
                                                                            />

                                                                            {evidencesForm.errors.evidences && (
                                                                                <div
                                                                                    className="invalid-feedback d-block">
                                                                                    {evidencesForm.errors.evidences}
                                                                                </div>
                                                                            )}

                                                                            <div className="file-validation-limit mt-3">
                                                                                <div>
                                                                                    <p>
                                                                                        <span
                                                                                            className="me-1"
                                                                                        >
                                                                                            Accepted File Types:
                                                                                        </span>
                                                                                        .doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.png,.jpeg,.gif,.pdf,.msg,.eml
                                                                                    </p>
                                                                                </div>
                                                                                <p>
                                                                                    <span className="me-1">
                                                                                        Maximum File
                                                                                        Size:{" "}
                                                                                    </span>
                                                                                    15MB
                                                                                </p>
                                                                                <p>
                                                                                    <span className="me-1">
                                                                                        Maximum
                                                                                        Character
                                                                                        Length:{" "}
                                                                                    </span>
                                                                                    250
                                                                                </p>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </Tab.Pane>
                                                            ) : null}
                                                            {globalSetting.allow_document_link ? (
                                                                <Tab.Pane eventKey="create-link">
                                                                    <div className="row mb-3">
                                                                        <label
                                                                            className="col-xl-2 col-lg-2 col-md-2 form-label col-form-label"
                                                                            htmlFor="name"
                                                                        >
                                                                            Name:
                                                                            <span
                                                                                className="required text-danger">*</span>
                                                                        </label>
                                                                        <div className="col-xl-10 col-lg-10 col-md-10">
                                                                            <input
                                                                                value={evidencesForm.data.name}
                                                                                onChange={(e) => {
                                                                                    checkCreateNameValue(e)
                                                                                    evidencesForm.setData("name", e.target.value);
                                                                                }}
                                                                                type="text"
                                                                                name="name"
                                                                                className="form-control"
                                                                            />
                                                                            {evidencesForm.errors.name && (
                                                                                <div
                                                                                    className="invalid-feedback d-block"
                                                                                >
                                                                                    {evidencesForm.errors.name}
                                                                                </div>
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                    <div className="row mb-3">
                                                                        <label
                                                                            className="col-xl-2 col-lg-2 col-md-2 form-label col-form-label"
                                                                            htmlFor="link"
                                                                        >
                                                                            Link:
                                                                            <span
                                                                                className="required text-danger">*</span>
                                                                        </label>
                                                                        <div className="col-xl-10 col-lg-10 col-md-10">
                                                                            <input
                                                                                value={evidencesForm.data.link}
                                                                                onChange={(e) => {
                                                                                    checkCreateLinkValue(e)
                                                                                    evidencesForm.setData("link", e.target.value);
                                                                                }}
                                                                                type="text"
                                                                                name="link"
                                                                                className="form-control"
                                                                                id="link"
                                                                            />
                                                                            {evidencesForm.errors.link && (
                                                                                <div
                                                                                    className="invalid-feedback d-block">
                                                                                    {evidencesForm.errors.link}
                                                                                </div>
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                </Tab.Pane>
                                                            ) : null}
                                                            <Tab.Pane eventKey="existing-control">
                                                                <div className="row mb-3">
                                                                    <label
                                                                        className="col-xl-2 col-lg-2 col-md-2 form-label col-form-label"
                                                                        htmlFor="name"
                                                                    >
                                                                        Standards:
                                                                        <span className="required text-danger">*</span>
                                                                    </label>
                                                                    <div className="col-xl-10 col-lg-10 col-md-10">
                                                                        <input
                                                                            type="text"
                                                                            className="form-control"
                                                                            value={selectedRow ? selectedRow.standard : ''}
                                                                            disabled
                                                                        />
                                                                    </div>
                                                                </div>
                                                                <div className="row mb-3">
                                                                    <label
                                                                        className="col-xl-2 col-lg-2 col-md-2 form-label col-form-label"
                                                                        htmlFor="link"
                                                                    >
                                                                        Projects:
                                                                        <span className="required text-danger">*</span>
                                                                    </label>
                                                                    <div className="col-xl-10 col-lg-10 col-md-10">
                                                                        <input
                                                                            type="text"
                                                                            className="form-control"
                                                                            value={selectedRow ? selectedRow.project_name : ''}
                                                                            disabled
                                                                        />
                                                                    </div>
                                                                </div>
                                                                <div className="row mb-3">
                                                                    <label
                                                                        className="col-xl-2 col-lg-2 col-md-2 form-label col-form-label"
                                                                        htmlFor="link"
                                                                    >
                                                                        Controls:
                                                                        <span className="required text-danger">*</span>
                                                                    </label>
                                                                    <div className="col-xl-10 col-lg-10 col-md-10">
                                                                        <input
                                                                            type="text"
                                                                            className="form-control"
                                                                            value={selectedRow ? selectedRow.control_name : ''}
                                                                            disabled
                                                                        />
                                                                        {evidencesForm.errors.linked_to_project_control_id && (
                                                                            <div className="invalid-feedback d-block">
                                                                                {evidencesForm.errors.linked_to_project_control_id}
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            </Tab.Pane>
                                                            <Tab.Pane eventKey="text-input">
                                                                <div className="row mb-3">
                                                                    <label
                                                                        className="col-xl-2 col-lg-2 col-md-2 form-label col-form-label"
                                                                        htmlFor="text_evidence_name"
                                                                    >
                                                                        Name:
                                                                        <span className="required text-danger">*</span>
                                                                    </label>
                                                                    <div className="col-xl-10 col-lg-10 col-md-10">
                                                                        <input
                                                                            type="text"
                                                                            name="text_evidence_name"
                                                                            className="form-control"
                                                                            onChange={(e) => {
                                                                                checkInputNameValue(e)
                                                                                evidencesForm.setData("text_evidence_name", e.target.value);
                                                                            }}
                                                                            value={evidencesForm.data.text_evidence_name}
                                                                        />
                                                                        {evidencesForm.errors.text_evidence_name && (
                                                                            <div className="invalid-feedback d-block">
                                                                                {evidencesForm.errors.text_evidence_name}
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                                <div
                                                                    className="row mb-3"
                                                                    id="evidence-section"
                                                                >
                                                                    <label
                                                                        className="col-xl-2 col-lg-2 col-md-2 form-label col-form-label"
                                                                        htmlFor="text_evidence"
                                                                    >
                                                                        Text:
                                                                        <span className="required text-danger">*</span>
                                                                    </label>
                                                                    <div className="col-xl-10 col-lg-10 col-md-10">
                                                                        <textarea
                                                                            name="text_evidence"
                                                                            className="form-control send-message"
                                                                            rows="3"
                                                                            placeholder="Write your evidence text here ..."
                                                                            value={evidencesForm.data.text_evidence}
                                                                            onChange={(e) => {
                                                                                checkInputTextValue(e);
                                                                                evidencesForm.setData("text_evidence", e.target.value);
                                                                            }}
                                                                            autoFocus
                                                                        />
                                                                        {evidencesForm.errors.text_evidence && (
                                                                            <div className="invalid-feedback d-block">
                                                                                {evidencesForm.errors.text_evidence}
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            </Tab.Pane>
                                                        </Tab.Content>
                                                    </Tab.Container>

                                                    {(globalSetting.allow_document_link ||
                                                        globalSetting.allow_document_upload) && !hasLinkedEvidence ? (
                                                        <button
                                                            type="submit"
                                                            className="btn btn-primary float-end"
                                                            id="evidence-submit"
                                                            disabled={evidencesForm.processing}
                                                        >
                                                            {buttonValue}
                                                        </button>
                                                    ) : null}

                                                    {hasLinkedEvidence && selectedRowUpdated ?
                                                        <button
                                                            type="submit"
                                                            className="btn btn-primary float-end"
                                                            id="evidence-submit"
                                                            disabled={evidencesForm.processing}
                                                        >
                                                            {buttonValue}
                                                        </button>
                                                        :
                                                        hasLinkedEvidence ? (
                                                            <button
                                                                className="btn btn-primary float-end"
                                                                id="evidence-submit"
                                                                disabled={evidencesForm.processing}
                                                                onClick={handleExistingControlClick}
                                                            >
                                                                Amend link
                                                            </button>
                                                        ) : null
                                                    }
                                                </form>
                                            </div>
                                        </Accordion.Body>
                                    </Accordion.Item>
                                </Accordion>
                            </div>
                        ) : null}

                    {
                        hasComplianceRole &&
                            projectControl.automation !== 'technical' &&
                            mergedEvidences.filter((e) => e.status !== 'draft').length > 0 ? (
                            <>
                                <h4 className="pb-2 upload-text p-0">
                                    Uploaded Evidence for Control ID:{" "}
                                    {projectControl.controlId}
                                </h4>

                                <div className="uploaded-evidence-main p-2">
                                    <table
                                        className="table nowrap text-center table-bordered border-light low-padding w-100"
                                    >
                                        <thead className="table-light">
                                            <tr>
                                                <th>Name</th>
                                                {projectControl.automation !== 'awareness' &&
                                                    <th>{projectControl.automation === 'document' ? 'Status' : 'Task Deadline'}</th>}
                                                {(projectControl.automation === 'awareness' || projectControl.automation === 'document') &&
                                                    <th>Type</th>}
                                                {projectControl.automation !== 'awareness' && <th>Created On</th>}
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody className="table-light name-table__overflow">
                                            {(projectControl.automation !== 'document' && projectControl.automation !== 'awareness') &&
                                                (mergedEvidences.length > 0 ? (
                                                    mergedEvidences
                                                        .filter((evidence) => evidence.type !== 'additional')
                                                        .map(
                                                            (evidence, index) => (
                                                                <EvidenceItem
                                                                    key={index}
                                                                    evidence={evidence}
                                                                    handleEvidenceAction={handleEvidenceAction}
                                                                />
                                                            )
                                                        )
                                                ) : (
                                                    <tr className="odd">
                                                        <td
                                                            valign="top"
                                                            colSpan="4"
                                                            className="dataTables_empty"
                                                        >
                                                            No data available in table
                                                        </td>
                                                    </tr>
                                                ))}

                                            {/*For Document*/}
                                            {(projectControl.automation === 'document' || projectControl.automation === 'awareness') &&
                                                ((mergedEvidences.length > 0) ? (
                                                    mergedEvidences
                                                        .map(
                                                            (evidence, index) => (
                                                                <EvidenceItem
                                                                    key={index}
                                                                    evidence={evidence}
                                                                    handleEvidenceAction={
                                                                        handleEvidenceAction
                                                                    }
                                                                />
                                                            )
                                                        )
                                                ) : (
                                                    <tr className="odd">
                                                        <td
                                                            valign="top"
                                                            colSpan="5"
                                                            className="dataTables_empty"
                                                        >
                                                            No data available in table
                                                        </td>
                                                    </tr>
                                                ))}
                                        </tbody>
                                    </table>
                                </div>
                            </>
                        ) : null
                    }

                    {
                        projectControl.automation === 'none' &&
                            projectControl.status === 'Implemented' ? (
                            <div className="request-evidence text-center">
                                {
                                    projectControl.amend_status === 'requested_responsible' &&
                                        authUser.id === projectControl.approver ? (
                                        <>
                                            <button
                                                type="button"
                                                className="btn btn-primary my-2 me-1 request-decision-btn"
                                                id="accept-amendment"
                                                disabled={acceptingAmendment}
                                                onClick={handleAcceptAmendment}
                                            >Accept evidence amendment request
                                            </button>
                                            <button
                                                type="button"
                                                className="btn btn-primary my-2 request-decision-btn"
                                                id="reject-amendment"
                                                onClick={() => setRejectAmendModalShow(true)}
                                            >
                                                Reject evidence amendment request
                                            </button>
                                        </>
                                    ) : (
                                        (
                                            (['solved', 'rejected', null].includes(projectControl.amend_status)) &&
                                            (authUserRoles.some(role => allowedRoles.includes(role)) || [projectControl.approver, projectControl.responsible].includes(authUser.id))) ? (
                                            <button
                                                type="button"
                                                className="btn btn-primary my-2"
                                                id="request-amendment"
                                                onClick={() => setRequestAmendModalShow(true)}
                                            >
                                                Request evidence amendment
                                            </button>
                                        ) : null
                                    )
                                }
                            </div>
                        ) : null
                    }

                    {
                        projectControl.automation === 'technical' &&
                            mergedEvidences.filter((evidence) => evidence.type != 'json').length > 0 ? (
                            <div>
                                <h4 className="pb-2 upload-text p-0">
                                    Additional uploaded evidence for Control ID:{" "}
                                    {projectControl.controlId}
                                </h4>

                                <div className="uploaded-evidence-main p-2">
                                    <table
                                        className="table nowrap text-center table-bordered border-light low-padding w-100">
                                        <thead className="table-light">
                                            <tr>
                                                <th>Name</th>
                                                <th>{projectControl.automation === 'document' ? 'Status' : 'Task Deadline'}</th>
                                                <th>Created On</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody className="table-light name-table__overflow">
                                            {mergedEvidences
                                                .filter((evidence) => evidence.type == 'additional')
                                                .map(
                                                    (evidence, index) => (
                                                        <EvidenceItem
                                                            key={index}
                                                            evidence={evidence}
                                                            handleEvidenceAction={
                                                                handleEvidenceAction
                                                            }
                                                        />
                                                    ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ) : null
                    }
                </div>

                {projectControl.automation !== 'document' &&
                    <div className="col-xl-6" id="">
                        {projectControl.automation === 'awareness' && campaignDataLoaded && (
                            <>
                                {
                                    projectControl.status != 'Implemented' ? (
                                        <>
                                            {!hasPolicyRole && !campaignDataId &&
                                                <Alert
                                                    variant={"info d-flex align-items-center justify-content-between awareness-alert mt-2 mb-2"}>
                                                    <span>
                                                        <i className="fas fa-exclamation-circle flex-shrink-0 me-1" />
                                                        <span>To be able to run the campaign the policy admin or global admin role is required. Implement this control manually or re-assign this control to a user with the adequate access level.</span>
                                                    </span>
                                                </Alert>
                                            }
                                        </>
                                    ) : null
                                }

                                <div
                                    className={`mt-2 mb-2 ${hasPolicyRole || projectControl.status == 'Implemented' || campaignDataId ? '' : 'awareness-form-overlay'}`}
                                    style={{ padding: '12px 0' }}
                                >
                                    {
                                        !campaignDataId && projectControl.is_campaign_run ? (
                                            <>
                                                {
                                                    hasPolicyRole ? (
                                                        <>
                                                            {
                                                                authUser.id === projectControl.responsible ? (
                                                                    <>
                                                                        <Alert
                                                                            variant={"warning d-flex align-items-center justify-content-between"}>
                                                                            <span>
                                                                                <i className="fas fa-exclamation-circle flex-shrink-0 me-1" />
                                                                                <span>Launch an awareness campaign using the below form to implement this control.</span>
                                                                            </span>
                                                                        </Alert>
                                                                        <AddAwarenessCampaignForm
                                                                            searchQuery={searchQuery}
                                                                            campaignTypeFilter={campaignTypeFilter}
                                                                            ref={addCampaignFormRef}
                                                                            setIsFormSubmitting={setIsFormSubmitting}
                                                                            policies={policies}
                                                                            groups={groups}
                                                                            groupUsers={groupUsers}
                                                                            controlId={controlId}
                                                                            projectId={projectControl.project_id}
                                                                            ssoIsEnabled={ssoIsEnabled}
                                                                        />
                                                                        <LoadingButton
                                                                            className="btn btn-primary waves-effect waves-light float-end"
                                                                            onClick={() => addCampaignFormRef.current.launchCampaign()}
                                                                            loading={isFormSubmitting}
                                                                        >
                                                                            Run Campaign
                                                                        </LoadingButton>
                                                                    </>
                                                                ) : null
                                                            }
                                                        </>
                                                    ) : null
                                                }

                                                {!hasPolicyRole &&
                                                    <>
                                                        <AddAwarenessCampaignForm
                                                            searchQuery={searchQuery}
                                                            campaignTypeFilter={campaignTypeFilter}
                                                            ref={addCampaignFormRef}
                                                            setIsFormSubmitting={setIsFormSubmitting}
                                                            policies={policies}
                                                            groups={groups}
                                                            groupUsers={groupUsers}
                                                            controlId={controlId}
                                                            projectId={projectControl.project_id}
                                                            ssoIsEnabled={ssoIsEnabled}
                                                            overlayAdded={true}
                                                        />

                                                        <LoadingButton
                                                            className="btn btn-primary waves-effect waves-light float-end mb-3"
                                                        >
                                                            Run Campaign
                                                        </LoadingButton>
                                                    </>
                                                }
                                            </>
                                        ) : (
                                            <>
                                                {(campaignDataId) && hasComplianceRole &&
                                                    <>
                                                        <div>
                                                            {
                                                                authUser.id !== campaignOwnerId &&
                                                                    projectControl.status === 'Implemented' ? (
                                                                    <Alert
                                                                        variant={"info d-flex align-items-center justify-content-between mt-2 mb-2"}>
                                                                        <span>
                                                                            <i className="fas fa-exclamation-circle flex-shrink-0 me-1" />
                                                                            <span>This control was implemented as a result of the awareness campaign ran by <b>{campaignOwnerName}.</b></span>
                                                                        </span>
                                                                    </Alert>
                                                                ) : null
                                                            }

                                                            {
                                                                authUser.id !== campaignOwnerId &&
                                                                    authUser.department_name !== campaignOwnerDepartmentName &&
                                                                    projectControl.status === 'Not Implemented' ? (
                                                                    <Alert
                                                                        variant="info d-flex align-items-center justify-content-between mt-2 mb-2">
                                                                        <span>
                                                                            <i className="fas fa-exclamation-circle flex-shrink-0 me-1" />
                                                                            <span>This campaign is already ran by <b>{campaignOwnerName}</b> from <b>{campaignOwnerDepartmentName ?? 'Top Organization'}.</b></span>
                                                                        </span>
                                                                    </Alert>
                                                                ) : null
                                                            }

                                                            <h4 className="comment-text pb-2 float-start">Document</h4>

                                                            {authUser.department_name === campaignOwnerDepartmentName &&
                                                                <Link
                                                                    href={route('policy-management.campaigns.show', campaignDataId)}
                                                                    className={`btn btn-sm btn-primary mt-1 mb-1 float-end campaign-details`}
                                                                >
                                                                    Campaign details
                                                                </Link>
                                                            }
                                                        </div>
                                                        <div className="clearfix" />
                                                        <div className="shadow overflow-hidden">
                                                            <SizeMe>
                                                                {({ size }) => (
                                                                    <Document
                                                                        file={route('policy-management.campaigns.export-awareness-pdf', {
                                                                            id: campaignDataId,
                                                                        })}
                                                                        onLoadSuccess={onDocumentLoadSuccess}
                                                                    >
                                                                        <Page
                                                                            pageNumber={pageNumber}
                                                                            width={size.width ? size.width : 1}
                                                                        />
                                                                    </Document>
                                                                )}
                                                            </SizeMe>

                                                            {numPages &&
                                                                <>
                                                                    <p className="d-flex align-items-center justify-content-center">
                                                                        Page {pageNumber} of {numPages}
                                                                    </p>
                                                                    <nav aria-label="pdf-pagination"
                                                                        className="d-flex align-items-center justify-content-center">
                                                                        <ul className="pagination pagination-sm">
                                                                            {
                                                                                pageNumber === 1 &&
                                                                                <li className={`page-item disabled`}>
                                                                                    <button
                                                                                        className="page-link"
                                                                                        onClick={() => setPageNumber(pageNumber - 1)}
                                                                                    >
                                                                                        Previous
                                                                                    </button>
                                                                                </li>
                                                                            }
                                                                            {
                                                                                pageNumber !== 1 &&
                                                                                <li className={`page-item`}>
                                                                                    <button
                                                                                        className="page-link"
                                                                                        onClick={() => setPageNumber(pageNumber - 1)}
                                                                                    >
                                                                                        Previous
                                                                                    </button>
                                                                                </li>
                                                                            }
                                                                            {
                                                                                pageNumber === numPages &&
                                                                                <li className={`page-item disabled`}>
                                                                                    <button
                                                                                        className="page-link"
                                                                                        onClick={() => setPageNumber(pageNumber + 1)}
                                                                                    >
                                                                                        Next
                                                                                    </button>
                                                                                </li>
                                                                            }
                                                                            {
                                                                                pageNumber !== numPages &&
                                                                                <li className={`page-item`}>
                                                                                    <button
                                                                                        className="page-link"
                                                                                        onClick={() => setPageNumber(pageNumber + 1)}
                                                                                    >
                                                                                        Next
                                                                                    </button>
                                                                                </li>
                                                                            }
                                                                        </ul>
                                                                    </nav>
                                                                </>
                                                            }
                                                        </div>
                                                    </>}
                                            </>
                                        )
                                    }
                                </div>
                            </>
                        )}
                        {
                            projectControl.automation === 'technical' ? (
                                <div className="mt-2 mb-2">
                                    <Accordion defaultActiveKey="0">
                                        {integrations.map(({
                                            name,
                                            logo_link,
                                            integration_action: { action_name, id },
                                            integration_action_integration_control: {
                                                is_compliant,
                                                last_response,
                                                how_to_implement
                                            }
                                        }) => (
                                            <Accordion.Item key={id} eventKey="0">
                                                <Accordion.Header as="div">
                                                    <div className="d-flex w-100 justify-content-between">
                                                        <span className="d-inline-flex align-items-center fw-bold">
                                                            <img
                                                                src={logo_link} width={'22px'}
                                                                style={{ marginRight: '10px', borderRadius: '4px' }}
                                                            />
                                                            {
                                                                is_compliant ? (
                                                                    <i className="fe-check"
                                                                        style={{
                                                                            color: '#359f1d',
                                                                            fontSize: '18px'
                                                                        }}
                                                                    />
                                                                ) : (
                                                                    <i
                                                                        className="fe-x fe-3x"
                                                                        style={{ color: '#cf1110', fontSize: '18px' }}
                                                                    />
                                                                )
                                                            }
                                                            <span style={{ marginLeft: '5px' }}>{action_name} on {name}</span>
                                                        </span>
                                                        {
                                                            how_to_implement ? (
                                                                <OverlayTrigger
                                                                    placement={'top'}
                                                                    overlay={(props) => (
                                                                        <Tooltip {...props}>
                                                                            How to implement?
                                                                        </Tooltip>
                                                                    )}
                                                                >
                                                                    <a className="fw-light link-primary me-2"
                                                                        target="_blank"
                                                                        href={how_to_implement}>
                                                                        <i className="mdi mdi-help-circle-outline"
                                                                            style={{ fontSize: '20px' }}
                                                                            aria-hidden={true} />
                                                                    </a>
                                                                </OverlayTrigger>
                                                            ) : null
                                                        }
                                                    </div>
                                                </Accordion.Header>
                                                <Accordion.Body>
                                                    <div id="evidence-form-section">
                                                        <div className="json-evidence">
                                                            <pre
                                                                style={{
                                                                    maxHeight: '350px',
                                                                    margin: 0
                                                                }}
                                                            >
                                                                {JSON.stringify(JSON.parse(last_response ?? '[{"message": "No configuration available"}]'), undefined, 2)}
                                                            </pre>
                                                        </div>
                                                    </div>
                                                    {is_compliant ? (
                                                        <div class="d-flex justify-content-center">
                                                            <div className='d-flex p-1 mt-1'>
                                                                <Button
                                                                    title='Download Evidence'
                                                                    className='btn  btn-secondary download-button'
                                                                    onClick={
                                                                        () => downloadTxtFile(name, action_name, last_response)
                                                                    }
                                                                ><i className="mdi mdi-download-outline font-12 me-1" />Download</Button>
                                                            </div>
                                                        </div>
                                                    )
                                                        : null}
                                                </Accordion.Body>
                                            </Accordion.Item>
                                        ))}
                                    </Accordion>
                                </div>
                            ) : null
                        }
                        {
                            projectControl.automation === 'none' ? (
                                <>
                                    {
                                        (projectControl.required_evidence && (authUser.id === projectControl.responsible || authUser.id === projectControl.approver)) ? (
                                            <>
                                                <h4 className="comment-text pb-2">Required Evidence</h4>
                                                <div className="comment-box">
                                                    <p dangerouslySetInnerHTML={{ __html: projectControl.required_evidence }} />
                                                </div>
                                            </>
                                        ) : null
                                    }

                                    {
                                        (comments.length > 0 || authUser.id === projectControl.responsible || authUser.id === projectControl.approver) ? (
                                            <>
                                                <h4 className="comment-text pb-2">Comments</h4>
                                                <div className="comment-box" ref={commentsBoxRef}>
                                                    {
                                                        comments.length > 0 ?
                                                            comments.map((comment) => (
                                                                <CommentItem key={comment.id} comment={comment} />)) :
                                                            <p>No comments available</p>
                                                    }
                                                </div>
                                            </>
                                        ) : null
                                    }

                                    {
                                        authUserRoles.some((role) => allowedRoles.includes(role)) &&
                                            (authUser.id === projectControl.responsible || authUser.id === projectControl.approver) ? (
                                            <div className="post-comment clearfix">
                                                <form
                                                    id="control-comment-form"
                                                    onSubmit={handleOnSubmitComment}
                                                    method="POST"
                                                >
                                                    <textarea
                                                        name="comment"
                                                        id="comment"
                                                        className="form-control send-message"
                                                        rows={2}
                                                        placeholder="Write a comment here ..."
                                                        autoFocus
                                                        value={commentForm.data.comment}
                                                        onChange={(e) => commentForm.setData("comment", e.target.value)}
                                                    />
                                                    {commentForm.errors.comment ? (
                                                        <div className="invalid-feedback d-block">
                                                            {commentForm.errors.comment}
                                                        </div>
                                                    ) : null}
                                                    <button
                                                        type="submit"
                                                        disabled={commentForm.processing}
                                                        className="float-end btn btn-primary my-2"
                                                    >
                                                        Comment
                                                    </button>
                                                </form>
                                            </div>
                                        ) : null}
                                </>
                            ) : null
                        }

                        <div id="justification-section">
                            {((controlStatus === 'Rejected' || ["requested_approver", "requested_responsible", "accepted", "rejected"].includes(projectControl.amend_status)) && latestJustification !== null) ? (
                                <div
                                    className="toast show w-100 mb-2 shadow-sm"
                                    role="alert"
                                    aria-live="assertive"
                                    aria-atomic="true"
                                    data-toggle="toast"
                                >
                                    <div className="toast-header">
                                        <span className="avatar">
                                            {latestJustification.creator?.avatar}
                                        </span>
                                        <strong className="me-auto m-2">
                                            {latestJustification.creator_id === authUser.id ? "Me" : decodeHTMLEntity(latestJustification.creator.full_name)}
                                        </strong>
                                        <small>
                                            {diffForHumans(latestJustification.created_at)}
                                        </small>
                                    </div>
                                    <div className="toast-body readmore">
                                        <strong>Status: {justificationStatus}</strong>
                                        <p
                                            className="comment-box"
                                            dangerouslySetInnerHTML={{ __html: latestJustification.justification }}
                                        />
                                    </div>
                                </div>
                            ) : null}
                        </div>
                        {/*end justification*/}
                    </div>}

                {
                    hasComplianceRole &&
                        projectControl.automation === 'document' ? (
                        <div className="col-xl-6" id="control-document-wp">
                            <h4 className="comment-text pb-2">{projectControl.template.latest.status == "published" ? "Document" : "Document Draft"}</h4>
                            <div className="shadow overflow-hidden">
                                <SizeMe>
                                    {({ size }) => (
                                        <Document
                                            file={route('documents.export', {
                                                id: projectControl.document_template_id,
                                                data_scope: projectControl.self_data_scope
                                            })}
                                            onLoadSuccess={onDocumentLoadSuccess}
                                        >
                                            <Page pageNumber={pageNumber} width={size.width ? size.width : 1} />
                                        </Document>
                                    )}
                                </SizeMe>

                                {numPages &&
                                    <>
                                        <p className="d-flex align-items-center justify-content-center">
                                            Page {pageNumber} of {numPages}
                                        </p>
                                        <nav aria-label="pdf-pagination"
                                            className="d-flex align-items-center justify-content-center">
                                            <ul className="pagination pagination-sm">
                                                <li className={`page-item ${pageNumber === 1 ? 'disabled' : ''}`}>
                                                    <button
                                                        className="page-link"
                                                        onClick={() => setPageNumber(pageNumber - 1)}
                                                    >
                                                        Previous
                                                    </button>
                                                </li>
                                                <li className={`page-item ${pageNumber === numPages ? 'disabled' : ''}`}>
                                                    <button
                                                        className="page-link"
                                                        onClick={() => setPageNumber(pageNumber + 1)}
                                                    >
                                                        Next
                                                    </button>
                                                </li>
                                            </ul>
                                        </nav>
                                    </>
                                }
                            </div>
                        </div>
                    ) : null
                }
                {/* task right ends */}
            </div>

            {/*    NEW*/}
            <div
                className="d-flex justify-content-center justify-content-sm-center justify-content-md-end  mt-4"
                id="evidence-submit-buttons-wp"
            >
                {
                    authUserRoles.some((role) => allowedRoles.includes(role)) &&
                        authUser.id === projectControl.responsible &&
                        !hasLinkedEvidence &&
                        projectControl.isEligibleForReview ? (
                        <form
                            onSubmit={handleOnSubmitReview}
                            id="submit-for-review"
                        >
                            {
                                !projectControl.isEligibleForReview ?
                                    <button
                                        type="submit"
                                        className="btn btn-primary"
                                        disabled="disabled"
                                    >
                                        Submit for review
                                    </button>
                                    :
                                    <LoadingButton
                                        className="btn btn-primary waves-effect waves-light"
                                        loading={isReviewSubmitting}
                                    >
                                        Submit for review
                                    </LoadingButton>
                            }
                        </form>
                    ) : null
                }

                {
                    authUserRoles.some((role) => allowedRoles.includes(role)) &&
                        authUser.id === projectControl.approver &&
                        projectControl.status === "Under Review" &&
                        !hasLinkedEvidence ? (
                        <>
                            <LoadingButton
                                className="btn btn-primary waves-effect waves-light"
                                onClick={handleApproveEvidence}
                                loading={iseApproving}
                            >
                                Approve
                            </LoadingButton>
                            <button
                                type="button"
                                className="btn btn-primary mx-3"
                                id="reject-btn"
                                onClick={() => setRejectModalShow(true)}
                            >
                                Reject
                            </button>
                        </>
                    ) : null}
            </div>
        </div>
    );
};

export default TasksTab;
