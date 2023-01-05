import React, {Fragment} from 'react';
import Modal from 'react-bootstrap/Modal'
import { useSelector } from 'react-redux';

function ReportGenerateLoader(props) {
    const {show, title} = useSelector(state => state.reportGenerateLoaderReducer)
    return (
        <Fragment>
            <Modal
                style={{ zIndex: '9999999' }}
                show={show}
                aria-labelledby="myCenterModalLabel"
                id="downlodReportModal"
                dialogClassName="modal-dialog-centered"
            >
                <Modal.Body>
                <div id="animationSandbox" className="p-2 text-center">
                    <div className="spinner-border text-success m-2" role="status" >
                    </div>
                    <p>{title}</p>
                </div>
                </Modal.Body>
            </Modal>
        </Fragment>
    );
}

export default ReportGenerateLoader;
