import React, {useEffect} from "react";

import {useSelector} from "react-redux";
import {useForm, usePage} from "@inertiajs/inertia-react";
import {Inertia} from "@inertiajs/inertia";
import {Modal} from "react-bootstrap";

import LoadingButton from "../../../../../common/loading-button/LoadingButton";

const RequestAmendmentModal = ({show, onClose}) => {
    const appDataScope = useSelector(state => state.appDataScope.selectedDataScope.value);
    const {authUser, projectControl, project} = usePage().props;
    const requested_by = authUser.id === projectControl.responsible ? 'responsible' : 'approver';
    const {data, setData, post, reset, processing} = useForm({
        requested_by,
        justification: '',
        data_scope: appDataScope
    });

    const handleSubmit = e => {
        e.preventDefault();
        post(route('compliance.project-controls-request-amendment', [project.id, projectControl.id]), {
            onSuccess: () => {
                Inertia.reload();
                onClose();
            }
        });
    }

    React.useEffect(() => {
        if (!show) {
            // reset the form values
            reset();
        }
    }, [show])

    useEffect(() => {
        localStorage.setItem('activeTab', 'controls');
        localStorage.setItem('documentRedirectBack', window.location.pathname);
    }, [])

    useEffect(() => {
        return Inertia.on('before', e => {
            // check where user is going
            if (!((e.detail.visit.url.href).includes("/compliance/projects/") || (e.detail.visit.url.href).includes("/documents"))) {
                localStorage.removeItem("controlPerPage");
                localStorage.removeItem("controlCurrentPage");
            }
        });
    });

    return (
        <Modal onHide={onClose} show={show} centered>
            <Modal.Header closeButton>
                <Modal.Title>Request evidence amendment</Modal.Title>
            </Modal.Header>
            <form onSubmit={handleSubmit} method="post">
                <Modal.Body>
                    <h4 className="mt-0 mb-4">Justification Message</h4>
                    <div className="row">
                        <div className="col-md-12">
                            <div className="form-group no-margin">
                            <textarea
                                className="form-control"
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
                <Modal.Footer>
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
}

export default RequestAmendmentModal;