import React from "react";

import {Inertia} from "@inertiajs/inertia";
import {useForm, usePage} from "@inertiajs/inertia-react";
import {Modal} from "react-bootstrap";

import LoadingButton from "../../../../../common/loading-button/LoadingButton";

const RejectModal = ({showModal, onClose}) => {
    const {project, projectControl} = usePage().props;
    const {processing, data, setData, post} = useForm({
        justification: "",
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(
            route("compliance.project-controls-review-reject", [
                project.id,
                projectControl.id,
            ]),
            {
                onSuccess: () => {
                    Inertia.reload();
                },
                onFinish: onClose,
            }
        );
    };

    return (
        <Modal show={showModal} onHide={onClose} centered>
            <Modal.Header className="px-3 pt-3 pb-3" closeButton>
                <Modal.Title className="my-0">
                    Reject Evidence Confirmation
                </Modal.Title>
            </Modal.Header>
            <h4 className={"ms-3"}>Justification Message</h4>
            <form onSubmit={handleSubmit} method="post" className="justification-form">
                <Modal.Body className={'p-3'}>
                    <div className="row">
                        <div className="col-md-12">
                            <div className="mb-3">
                            <textarea
                                className="form-control" name="justification"
                                id="justification_textarea"
                                placeholder="Write justification message here"
                                value={data.justification}
                                onChange={e => setData('justification', e.target.value)}
                                required
                            />
                            </div>
                        </div>
                    </div>
                </Modal.Body>
                <Modal.Footer className="px-3 pt-0 pb-3 d-flex justify-content-center">
                    <LoadingButton className="btn btn-primary mx-2 waves-effect waves-light"
                                   loading={processing}>Submit</LoadingButton>
                    <button
                        type="button"
                        className="btn btn-danger"
                        onClick={onClose}
                    >
                        Cancel
                    </button>
                </Modal.Footer>
            </form>
        </Modal>
    );
};

export default RejectModal;