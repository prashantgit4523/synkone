import React from "react";

import {Inertia} from "@inertiajs/inertia";
import {Link, usePage} from "@inertiajs/inertia-react";
import Swal from "sweetalert2";
import moment from "moment";

import DocumentAutomationEvidence from "./DocumentAutomationEvidence";

const EvidenceItem = ({evidence, handleEvidenceAction}) => {
    const {project, meta, projectControl} = usePage().props;

    const isControl = evidence.type === "control";
    const name = evidence.name;

    let url = null;
    let icon = "fe-link";
    let title = "Link";

    const handleEvidenceDelete = (evidenceDeleteLink) => {
        Swal.fire({
            title: "Are you sure?",
            text: "You won't be able to revert this!",
            showCancelButton: true,
            confirmButtonColor: "#f1556c",
            confirmButtonText: "Yes, delete it!",
            icon: 'warning',
            iconColor: '#f1556c',
        }).then((confirmed) => {
            if (confirmed.value) {
                axiosFetch.delete(evidenceDeleteLink).then(() => {
                    Swal.fire({
                        title: "Deleted!",
                        text: "Your file has been deleted.",
                        confirmButtonColor: "#b2dd4c",
                        icon: 'success',
                    });
                    Inertia.reload();
                });
            }
        });
    };

    switch (evidence.type) {
        case "additional":
        case "document":
            icon = "fe-download";
            title = "Download";
            url = route("compliance-project-control-evidences-download", [
                project.id,
                evidence.project_control_id,
                evidence.id,
            ]);
            break;
        case "control":
            url = route("project-control-linked-controls-evidences-view", [
                project.id,
                evidence.path,
                evidence.project_control_id,
            ]);
            break;
        case "link":
            url = evidence.path;
            icon = 'fe-eye';
            break;
        case 'text':
            url = '#';
            icon = 'fe-type';
            title = 'Display';
            break;
        case "awareness":
            icon = "fe-download";
            title = "Download";
            url = route("policy-management.campaigns.export-awareness-pdf", [
                evidence.campaignId
            ]);
            break;
        case 'json':
            url = '#';
            icon = 'fe-type';
            title = 'JSON Evidence';
            break;
    }
    let dom;
    if (projectControl.automation === 'document') {
        dom = <td>-</td>;
    } else {
        dom = <td>{moment(projectControl.deadline).format('D MMM YYYY')}</td>;
    }

    let document_type = 'Additional';
    if (projectControl.automation === 'awareness') {
        document_type = evidence.type == 'awareness' ? 'Default' : 'Additional';
    }

    return (
        evidence.document_template_id
            ? <DocumentAutomationEvidence evidence={evidence}/> :
            <tr>
                <td>{isControl ? (
                    <>
                        This control is linked to <Link className="link-primary"
                                                        href={route('project-control-linked-controls-evidences-view', [project.id, evidence.path, evidence.project_control_id])}>{name}</Link>
                    </>
                ) : name}</td>

                {projectControl.automation !== 'awareness' && dom}
                {(projectControl.automation === 'awareness' || projectControl.automation === 'document') &&
                    <td>{document_type}</td>}
                {projectControl.automation !== 'awareness' &&
                    <td>{moment(evidence.created_at).format('D MMM YYYY')}</td>}
                <td>
                    <div className="btn-group">
                        <button
                            className="btn btn bg-secondary text-white btn-xs waves-effect waves-light"
                            title={title}
                            onClick={() =>
                                handleEvidenceAction(evidence.type, {
                                    url,
                                    name: evidence.name,
                                    text: evidence.text_evidence,
                                })
                            }
                        >
                            <i className={icon} style={{fontSize: "12px"}}/>
                        </button>

                        {(meta.evidence_delete_allowed && !evidence?.is_linked) ? (
                            <button
                                className='evidence-delete-link btn btn-danger text-white btn-xs waves-effect waves-light'
                                onClick={() => handleEvidenceDelete(route('compliance-project-control-evidences-delete', [project.id, projectControl.id, evidence.id]))}
                                title='Delete'><i className='fe-trash-2' style={{fontSize: '12px'}}/></button>
                        ) : null}
                    </div>
                </td>
            </tr>
    );
};

export default EvidenceItem;