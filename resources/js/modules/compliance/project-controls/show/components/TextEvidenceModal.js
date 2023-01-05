import React from "react";
import {Modal} from "react-bootstrap";

const TextEvidenceModal = ({showModal, onClose, heading = "", body = ""}) => {
    return (
        <Modal show={showModal} onHide={onClose} size={heading === 'JSON Evidence' ? 'lg' : 'xl'} centered>
            <Modal.Header className="px-3 pt-3 pb-0" closeButton>
                <Modal.Title className="my-0">{heading}</Modal.Title>
            </Modal.Header>
            <Modal.Body className="p-3">
                {heading === 'JSON Evidence' ? (
                    <div className="json-evidence">
                        <pre style={{maxHeight: '350px'}}>{JSON.stringify(JSON.parse(body), undefined, 2)}</pre>
                    </div>
                ) : body}
            </Modal.Body>
            <Modal.Footer className="px-3 pt-0 pb-3">
                <button className="btn btn-secondary" onClick={onClose}>
                    Close
                </button>
            </Modal.Footer>
        </Modal>
    );
};

export default TextEvidenceModal;