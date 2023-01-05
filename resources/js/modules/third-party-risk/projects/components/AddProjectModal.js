import React, {useEffect, useState} from "react";

import {useForm, usePage} from "@inertiajs/inertia-react";
import {useSelector} from "react-redux";

import moment from "moment-timezone";
import Modal from "react-bootstrap/Modal";
import Select from "../../../../common/custom-react-select/CustomReactSelect";
import Datetime from "react-datetime";

const AddProjectModal = ({show, handleClose, reload}) => {
    const {globalSetting, frequencies, timezones} = usePage().props;
    const [questionnaires, setQuestionnaires] = useState([]);
    const [vendors, setVendors] = useState([]);

    const appDataScope = useSelector(state => state.appDataScope.selectedDataScope.value);
    const {data, setData, errors, post, processing, reset, clearErrors, transform} = useForm({
        name: '',
        questionnaire_id: null,
        vendor_id: null,
        frequency: frequencies[0].value,
        timezone: globalSetting.timezone,
        launch_date: moment(),
        due_date: moment().add(1,'days'),
        data_scope: appDataScope
    });

    useEffect(() => {
        axiosFetch.get(route('third-party-risk.projects.options'), {
            params: {
                data_scope: appDataScope
            }
        }).then(({data}) => {
            setQuestionnaires(data.questionnaires);
            setVendors(data.vendors);
        })
    }, [appDataScope]);

    useEffect(() => {
        reset();
        clearErrors();
    }, [show])

    const handleSubmit = e => {
        e.preventDefault();

        const format = 'YYYY-MM-DD HH:mm:ss';

        let launchDate = data.launch_date.tz(globalSetting.timezone).format(format);
        launchDate = moment.tz(launchDate, data.timezone).utc().format(format);

        let dueDate =  data.due_date.tz(globalSetting.timezone).format(format);
        dueDate = moment.tz(dueDate, data.timezone).utc().format(format);

        transform((data) => ({
            ...data,
            launch_date: launchDate,
            due_date: dueDate
        }));

        post(route('third-party-risk.projects.store'), {
            onSuccess: () => {
                AlertBox({
                    title: "Project Scheduled!",
                    text: "This project has been scheduled for launch!",
                    // showCancelButton: true,
                    confirmButtonColor: '#b2dd4c',
                    confirmButtonText: 'OK',
                    closeOnConfirm: false,
                    icon: 'success',
                }, function () {
                    handleClose();
                    reload();
                });
            }
        });
    }

    const [tprProjectError,setTprProjectError] = useState({tprError: []});
    useEffect(()=>{
        let tprError  = {
            ...tprProjectError.tprError,
            name:errors.name,
            questionnaire_id:errors.questionnaire_id,
            vendor_id:errors.vendor_id,
            launch_date:errors.launch_date,
            due_date:errors.due_date,
            timezone:errors.timezone,
            frequency:errors.frequency,
        }
        setTprProjectError({tprError});
    },[errors]);

    return (
        <Modal
            size={'lg'}
            show={show}
            onHide={handleClose}
        >
            <Modal.Header className='px-3 pt-3 pb-0' closeButton>
                <Modal.Title className="my-0">New Project</Modal.Title>
            </Modal.Header>
            <form onSubmit={handleSubmit} method="post">
                <Modal.Body className="p-3">
                    <div className="row">
                        <div className="col-md-12 mb-3">
                            <label className="form-label">
                                Name <span className="required text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                className="form-control"
                                value={data.name}
                                onChange={(e) =>{ 
                                    setData('name', e.target.value)
                                    let tprError = {
                                        ...tprProjectError.tprError,
                                        name: ''
                                    }
                                    setTprProjectError({tprError})
                                }}
                            />
                            {tprProjectError.tprError?.name && (
                                <div className="invalid-feedback d-block">
                                    {tprProjectError.tprError.name}
                                </div>
                            )}
                        </div>
                        <div className="col-md-12 mb-3">
                            <label className="form-label">
                                Questionnaire <span className="required text-danger">*</span>
                            </label>
                            <Select
                                className="react-select"
                                classNamePrefix="react-select"
                                value={questionnaires.find(q => q.value === data.questionnaire_id)}
                                options={questionnaires}
                                onChange={(option) =>{ 
                                    setData('questionnaire_id', option.value)
                                    let tprError = {
                                        ...tprProjectError.tprError,
                                        questionnaire_id: ''
                                    }
                                    setTprProjectError({tprError})
                                }}
                            />
                            {tprProjectError.tprError?.questionnaire_id && (
                                <div className="invalid-feedback d-block">
                                    {tprProjectError.tprError.questionnaire_id}
                                </div>
                            )}
                        </div>
                        <div className="col-md-12 mb-3">
                            <label className="form-label">
                                Vendor <span className="required text-danger">*</span>
                            </label>
                            <Select
                                className="react-select"
                                classNamePrefix="react-select"
                                value={vendors.find(v => v.value === data.vendor_id)}
                                options={vendors}
                                onChange={(option) =>{ 
                                    setData('vendor_id', option.value)
                                    let tprError = {
                                        ...tprProjectError.tprError,
                                        vendor_id: ''
                                    }
                                    setTprProjectError({tprError})
                                }}
                            />
                            {tprProjectError.tprError?.vendor_id && (
                                <div className="invalid-feedback d-block">
                                    {tprProjectError.tprError.vendor_id}
                                </div>
                            )}
                        </div>
                    </div>
                    <div className="row">
                        <div className="col-md-4 mb-3">
                            <label className="form-label">
                                Launch Date <span className="required text-danger">*</span>
                            </label>
                            <Datetime
                                value={data.launch_date}
                                displayTimeZone={globalSetting.timezone}
                                dateFormat={'DD/MM/YYYY'}
                                onChange={(datetime) =>{
                                    setData('launch_date', datetime)
                                    let tprError = {
                                        ...tprProjectError.tprError,
                                        launch_date: ''
                                    }
                                    setTprProjectError({tprError})
                                }}
                                isValidDate={(currentDate) => currentDate.isAfter(moment().subtract(1, 'day'))}
                                inputProps={{
                                    readOnly: true
                                }}
                                closeOnSelect
                            />
                            {tprProjectError.tprError?.launch_date && (
                                <div className="invalid-feedback d-block">
                                    {tprProjectError.tprError.launch_date}
                                </div>
                            )}
                        </div>
                        <div className="col-md-4 mb-3">
                            <label className="form-label">
                                Due Date <span className="required text-danger">*</span>
                            </label>
                            <Datetime
                                value={data.due_date}
                                displayTimeZone={globalSetting.timezone}
                                dateFormat={'DD/MM/YYYY'}
                                onChange={(datetime) =>{
                                    setData('due_date', datetime)
                                    let tprError = {
                                        ...tprProjectError.tprError,
                                        due_date: ''
                                    }
                                    setTprProjectError({tprError})
                                }}
                                isValidDate={(currentDate) => currentDate.isAfter(data.launch_date)}
                                inputProps={{
                                    readOnly: true
                                }}
                                closeOnSelect
                            />
                            {tprProjectError.tprError?.due_date && (
                                <div className="invalid-feedback d-block">
                                    {tprProjectError.tprError.due_date}
                                </div>
                            )}
                        </div>
                        <div className="col-md-4 mb-3">
                            <label className="form-label">
                                Time Zone <span className="required text-danger">*</span>
                            </label>
                            <Select
                                className="react-select"
                                classNamePrefix="react-select"
                                value={timezones.find(t => t.value === data.timezone)}
                                options={timezones}
                                onChange={(option) =>{
                                    setData('timezone', option.value)
                                    let tprError = {
                                        ...tprProjectError.tprError,
                                        timezone: ''
                                    }
                                    setTprProjectError({tprError})
                                }}
                            />
                            {tprProjectError.tprError?.timezone && (
                                <div className="invalid-feedback d-block">
                                    {tprProjectError.tprError.timezone}
                                </div>
                            )}
                        </div>
                    </div>
                    <div className="row">
                        <div className="col-md-12">
                            <label className="form-label">
                                Frequency <span className="required text-danger">*</span>
                            </label>
                            <Select
                                className="react-select"
                                classNamePrefix="react-select"
                                options={frequencies}
                                value={frequencies.find(f => f.value === data.frequency)}
                                onChange={(option) =>{
                                    setData('frequency', option.value)
                                    let tprError = {
                                        ...tprProjectError.tprError,
                                        frequency: ''
                                    }
                                    setTprProjectError({tprError})
                                }}
                            />
                            {tprProjectError.tprError?.frequency && (
                                <div className="invalid-feedback d-block">
                                    {tprProjectError.tprError.frequency}
                                </div>
                            )}
                        </div>
                    </div>
                </Modal.Body>
                <Modal.Footer className='px-3 pt-0 pb-3'>
                    <button type="button" className="btn btn-secondary" onClick={handleClose}>
                        Close
                    </button>
                    <button type="submit" className="btn btn-primary" disabled={processing}>Launch Project</button>
                </Modal.Footer>
            </form>
        </Modal>
    )
};

export default AddProjectModal;
