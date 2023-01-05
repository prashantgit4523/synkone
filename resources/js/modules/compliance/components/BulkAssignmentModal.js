import React, {useEffect, useLayoutEffect, useRef, useState} from 'react';

import useDataTable from "../../../custom-hooks/useDataTable";
import Select from "../../../common/custom-react-select/CustomReactSelect";
import Flatpickr from "react-flatpickr";

import {OverlayTrigger, Tooltip} from "react-bootstrap";
import {Inertia} from "@inertiajs/inertia";
import {useDispatch, useSelector} from "react-redux";
import moment from "moment";

import './styles.css';
import {updateTable} from "../../../store/slices/dataTableSlice";

const BulkAssignmentModal = ({
                                 tag,
                                 admins,
                                 frequencies,
                                 projectId,
                                 onStart = null,
                                 onFinish = null,
                                 onAssign = null
                             }) => {
    const {data, refresh, selectedRows, tag: newTag} = useDataTable(tag);
    const appDataScope = useSelector(state => state.appDataScope.selectedDataScope.value);


    const [isApplicable, setIsApplicable] = useState(true);
    const [selectedResponsible, setSelectedResponsible] = useState(null);
    const [selectedApprover, setSelectedApprover] = useState(null);
    const [selectedFrequency, setSelectedFrequency] = useState(null);
    const [selectedDeadline, setSelectedDeadline] = useState(null);
    const [isOverriding, setIsOverriding] = useState(false);
    const [isAutomating, setIsAutomating] = useState(false);
    const [intersecting, setIntersecting] = useState(false);
    const dispatch = useDispatch();

    const flatPickrRef = useRef(null);
    const containerRef = useRef(null);
    const eventHandler = () => {
        const container = containerRef.current;
        if (container) {
            const {offsetTop} = container;
            if (offsetTop && offsetTop > 145) {
                return setIntersecting(true);
            }
            setIntersecting(false);
        }
    }

    const reset = (withApplicable = true) => {
        if (withApplicable) {
            setIsApplicable(true);
        }
        setSelectedResponsible(null);
        setSelectedApprover(null);
        setSelectedFrequency(null);
        setSelectedDeadline(null);
        setIsAutomating(false);
        setIntersecting(false);
    }

    useLayoutEffect(() => {
        window.addEventListener('scroll', eventHandler);
        return () => {
            window.removeEventListener('scroll', eventHandler);
            reset();
        };
    }, [containerRef.current, selectedRows?.length])

    useEffect(() => {
        if (!isApplicable) {
            reset(false);
        }

        if (flatPickrRef.current) {
            flatPickrRef.current.flatpickr._input.disabled = !isApplicable;
        }
    }, [isApplicable])

    if (!selectedRows?.length) return <></>;

    const automatedControls = selectedRows.filter(r => r.automation !== 'none' && r.applicable && r.is_editable);
    const automationEnabledControls = selectedRows.filter(r => r.automation === 'none' && r.document_template_id && r.applicable && r.is_editable);

    const handleOverrideToManual = () => {
        const responsible = selectedResponsible.value;
        const approver = selectedApprover.value;

        let frequency = selectedFrequency?.value;
        if (!frequency) {
            frequency = 'One-Time';
        }

        let deadline = selectedDeadline;
        if (!deadline) {
            deadline = new Date().toISOString().split('T')[0];
        }

        if (typeof onStart === 'function') {
            onStart();
        }

        AlertBox({
            title: 'Are you sure that you want to bulk-change the controls to manual?',
            text: 'This action is irreversible, all automated implemented controls will turn not implemented for this specific project.',
            confirmButtonColor: '#f1556c',
            allowOutsideClick: false,
            icon: 'warning',
            iconColor: '#f1556c',
            showCancelButton: true,
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel'
        }, function (result) {
            if (result.isConfirmed) {
                Inertia.post(route('compliance-project-override-to-manual', [projectId]), {
                    controls: automatedControls.map(c => c.id),
                    data_scope: appDataScope,
                    automation: 'none',
                    manualOverride: 'no',
                    approver,
                    responsible,
                    frequency,
                    deadline
                }, {
                    onStart: () => {
                        setIsOverriding(true);
                    },
                    onSuccess: () => {
                        setIsOverriding(false);
                        // setSelectedRows([]);
                        refresh();
                    },
                    onFinish: () => {
                        typeof onFinish === 'function' && onFinish();
                    }
                });
            }
        })
    }

    const handleBulkAutomate = () => {
        setIsAutomating(false);
        let deadline = selectedDeadline;
        if (!deadline) {
            deadline = new Date().toISOString().split('T')[0];
        }

        if (typeof onStart === 'function') {
            onStart();
        }

        Inertia.post(route('compliance-project-automate-controls', [projectId]), {
            controls: automationEnabledControls.map(c => c.id),
            data_scope: appDataScope,
            responsible: selectedResponsible?.value,
            automation: 'document',
            deadline
        }, {
            onStart: () => {
                setIsAutomating(true);
            },
            onSuccess: () => {
                // setSelectedRows([]);
                refresh();
                setIsAutomating(false);
            },
            onFinish: () => {
                typeof onFinish === 'function' && onFinish();
            }
        });
    }

    const handleBulkAssignment = () => {
        const results = data?.map(r => {
            if (!r.is_editable || !selectedRows.includes(r)) return r;

            const copy = {...r};

            const responsible = selectedResponsible?.value ?? copy.responsible;
            const approver = selectedApprover?.value ?? copy.approver;
            const frequency = selectedFrequency?.value ?? (copy.frequency ?? 'One-Time');
            const deadline = selectedDeadline ?? (copy.deadline ?? new Date().toISOString().split('T')[0]);

            copy.applicable = isApplicable;
            copy.deadline = deadline;

            if (responsible && responsible === approver) return copy;

            if (copy.automation === 'none' && responsible && approver) {
                return {
                    ...copy,
                    responsible,
                    approver,
                    frequency,
                }
            } else if (copy.automation === 'document' && responsible) {
                return {
                    ...copy,
                    responsible,
                    approver: null,
                }
            }

            return copy;
        })
            .reduce((prev, curr) => {
                return {...prev, [curr.id]: curr}
            }, {});

        onAssign && onAssign();
        dispatch(updateTable({tag: newTag, rows: results, selectedRowIds: [], selectAll: false}));
    }

    return (
        <div className={`bulk-assignment__container ${intersecting ? 'shadowed' : ''}`} ref={containerRef}>
            <div className="row">
                <div className="col-auto">
                    <div className="applicable__checkbox">
                        <div className="checkbox checkbox-success cursor-pointer">
                            <input
                                id="bulk-checkbox-applicable"
                                type="checkbox"
                                name="bulk-checkbox-applicable"
                                onChange={() => setIsApplicable(!isApplicable)}
                                checked={isApplicable}
                            />
                            <OverlayTrigger
                                popperConfig={{
                                    modifiers: [
                                        {
                                            name: 'offset',
                                            options: {
                                                offset: [-4, 10],
                                            },
                                        }
                                    ]
                                }}
                                overlay={<Tooltip id="tooltip-disabled">Applicable</Tooltip>}
                            >
                                <label htmlFor="bulk-checkbox-applicable"/>
                            </OverlayTrigger>
                        </div>
                    </div>
                </div>
                <div className="col">
                    <Select
                        options={admins.map(a => {
                            if (a.value === selectedApprover?.value) {
                                return {...a, isDisabled: true}
                            }
                            return a;
                        })}
                        onChange={setSelectedResponsible}
                        value={selectedResponsible}
                        placeholder="Responsible"
                        isDisabled={!isApplicable}
                        isClearable
                    />
                </div>
                <div className="col">
                    <Select
                        options={admins.map(a => {
                            if (a.value === selectedResponsible?.value) {
                                return {...a, isDisabled: true}
                            }
                            return a;
                        })}
                        onChange={setSelectedApprover}
                        value={selectedApprover}
                        placeholder="Approver"
                        isDisabled={!isApplicable}
                        isClearable
                    />
                </div>
                <div className="col">
                    <Flatpickr
                        className={`form-control flatpickr-date deadline-picker`}
                        options={{
                            enableTime: false,
                            dateFormat: 'Y-m-d',
                            altFormat: 'd-m-Y',
                            altInput: true,
                            minDate: 'today',
                            disable: [
                                function (date) {
                                    return moment(date).isBefore(moment().subtract(1, 'day'));
                                }
                            ]
                        }}
                        placeholder="Deadline"
                        value={selectedDeadline}
                        ref={flatPickrRef}
                        onChange={([deadline]) => {
                            const offset = deadline.getTimezoneOffset();
                            let value = new Date(deadline.getTime() - (offset * 60 * 1000));
                            const _deadline = value.toISOString().split('T')[0];
                            setSelectedDeadline(_deadline);
                        }}
                    />
                </div>
                <div className="col">
                    <Select
                        options={frequencies}
                        onChange={setSelectedFrequency}
                        value={selectedFrequency}
                        placeholder="Frequency"
                        isDisabled={!isApplicable}
                        isClearable
                    />
                </div>
                <div className="col">
                    <div className="btn-group" role="group">
                        <button className="btn btn-primary" onClick={handleBulkAssignment}>Assign</button>
                        <button
                            className="btn btn-secondary"
                            disabled={!selectedResponsible || !automationEnabledControls.length || isAutomating}
                            onClick={handleBulkAutomate}
                        >
                            Automate&nbsp;<span
                            className="badge text-secondary bg-light">{automationEnabledControls.length}</span>
                        </button>
                        <button
                            className="btn btn-danger"
                            disabled={!selectedApprover || !selectedResponsible || !automatedControls.length || isOverriding}
                            onClick={handleOverrideToManual}
                        >
                            Override&nbsp;<span
                            className="badge text-secondary bg-light">{automatedControls.length}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    )
}

export default BulkAssignmentModal;