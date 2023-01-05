import React from "react";
import moment from "moment";

const DocumentAutomationEvidence = ({evidence}) => {
    if (parseInt(evidence.version.split('.')[0]) !== 0) {
        return (
            <tr>
                <td>
                    {evidence.title} (v{evidence.version})
                </td>
                <td>{!evidence?.is_linked ? evidence.status.charAt(0).toUpperCase() + evidence.status.slice(1) : '-'}</td>
                {!evidence?.is_linked && <td>Default</td>}
                <td>{moment(evidence.created_at).format('D MMM YYYY')}</td>
                <td>
                    <a className="btn btn bg-secondary text-white btn-xs waves-effect waves-light" title="Download"
                       href={route('documents.export', {
                           id: evidence.document_template_id,
                           _query: {download: 'true', data_scope: evidence.data_scope}
                       })}>
                        <i className="fe-download"/>
                    </a>
                </td>
            </tr>
        )
    }
    return <></>;
}

export default DocumentAutomationEvidence;