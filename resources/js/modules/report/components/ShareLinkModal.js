import {Modal, OverlayTrigger, Popover} from "react-bootstrap";
import {Inertia} from "@inertiajs/inertia";
import {useState} from "react";

const ShareLinkModal = ({show, onClose, report}) => {
    const [reportStatus, setReportStatus] = useState(report.status);
    const [reportLink, setReportLink] = useState(report.share_link);

    const handleChange = (e) => setReportStatus(e.target.value);

    const handleSubmit = e => {
        e.preventDefault();
        
        Inertia.post(
            route('report.update'),
            {
                'status': reportStatus
            },
            {
                preserveState: true,
                onSuccess: () => {
                    onClose();
                }
            }
        );
    }

    const copyReportShareLink = () => {
        navigator.clipboard.writeText(reportLink);
    }

    const regenerateUrl = () => {
        axiosFetch(route('report.regenerateUrl'))
            .then((res) => {
                const el = document.getElementById('newUrl');
                el.innerHTML = res.data.message;

                if(res.data.success){
                    el.classList.add("valid-feedback", "d-block");

                    const element = document.getElementById('reportShareLink');

                    element.innerHTML = res.data.link;

                    setReportLink(res.data.link);
                }else{
                    el.classList.add("invalid-feedback", "d-block");
                }
            });
    }

    return (
        <Modal show={show} onHide={onClose} centered>
            <Modal.Header style={{paddingBottom: 0}} closeButton>
                <Modal.Title>Share</Modal.Title>
            </Modal.Header>
            <Modal.Body style={{paddingBottom: '1.5rem'}}>
                <form onSubmit={handleSubmit}>
                    <p className="font-14 clamp clamp-3">Send the custom URL to anyone to showcase your security posture. You have to enable the report sharing option for the link to work.</p>
                    <div className="row">
                    <pre className="col-10" id="reportShareLink">
                        {reportLink}
                    </pre>
                    <div className="col-1">
                    <OverlayTrigger
                    trigger="click"
                    key='bottom'
                    placement='bottom'
                    delay={{hide:0}}
                    rootClose
                    overlay={
                        <Popover id={`popover-positioned-bottom`}>
                        <Popover.Body>
                           Link copied to clipboard.
                        </Popover.Body>
                        </Popover>
                    }
                    >
                    <button type="button" onClick={copyReportShareLink}
                     className="btn btn-primary" id="copyLinkBtn"><i className="fe-copy"/></button>
                     </OverlayTrigger>
                    </div>
                    <div id="newUrl"></div>
                    </div>

                    <h5>Report Sharing</h5>
                    <div onChange={handleChange}>
                    <div className="mb-2 mt-2">
                    <label>
                    <input type="radio" name="status" value={1} defaultChecked={reportStatus === 1}/> Enabled
                    </label>
                    </div>
                    <div className="mb-3">
                    <label>
                    <input type="radio" name="status" value={0} defaultChecked={reportStatus === 0}/> Disabled
                    </label>
                    </div>
                    </div>
                    <h5>Regenerate URL</h5>
                    <p className="font-14 clamp clamp-3">Revoke user access by generating a new link. Users with the old link will not be able to access your report.</p>
                    <a href="#" onClick={regenerateUrl}>Regenerate URL</a>

                    <button className="btn btn-primary d-block m-auto" type="submit">Save</button>
                </form>
            </Modal.Body>
        </Modal>
    );
}

export default ShareLinkModal;