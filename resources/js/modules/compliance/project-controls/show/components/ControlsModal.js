import React, {useEffect, useRef, useState} from "react";

import {usePage} from "@inertiajs/inertia-react";
import {Modal} from "react-bootstrap";
import Switch from "rc-switch";
import Select from "../../../../../common/custom-react-select/CustomReactSelect";

import DataTable from "../../../../../common/custom-datatable/AppDataTable";
import useDataTable from "../../../../../custom-hooks/useDataTable";

const ControlsModal = ({showModal, onClose, onSelectRow}) => {
    const {projectControl, allStandards} = usePage().props;
    const {resetTable} = useDataTable(`tasks-tab-${projectControl.id}`);

    const [ajaxData, setAjaxData] = useState({});
    const [projects, setProjects] = useState([]);

    //selected project id and standard id
    const [selectedStandardId, setSelectedStandardId] = useState("");
    const [selectedProjectId, setSelectedProjectId] = useState("");

    const [refresh, setRefresh] = useState(true);
    const selectInputRef = useRef();

    useEffect(() => {
        setAjaxData({});
    }, [showModal]);

    const handleStandardChange = ({value}) => {
        selectInputRef.current.setValue({
            label: "Select Project",
            value: -1,
        });
        if (value === -1) {
            setSelectedStandardId("");
            setSelectedProjectId("");
            setProjects([]);
            return;
        }

        setSelectedStandardId(value);
        axiosFetch
            .get(route("compliance.tasks.get-projects-by-standards"), {
                params: {
                    standardId: value,
                },
            })
            .then((res) => {
                setProjects(res.data);
            });
    };

    const handleProjectChange = ({value}) => {
        if (value === -1) return setSelectedProjectId("");
        setSelectedProjectId(value);
    };

    useEffect(()=>{
        setProjects([]);
    },[onClose]);

    const handleSearch = () => {
        const data = {
            standard_filter: selectedStandardId,
            project_filter: selectedProjectId,
        };
        setAjaxData(data);
        resetTable();
        setRefresh(!refresh);
    };

    const columns = [
        {accessor: 'project_name', label: 'Project', priority: 2, position: 1, minWidth: 100, sortable: true},
        {accessor: 'standard', label: 'Standard', priority: 1, position: 2, minWidth: 140, sortable: true},
        {accessor: 'control_id', label: 'Control ID', priority: 2, position: 3, minWidth: 100, sortable: true, as: 'full_control_id'},
        {
            accessor: 'control_name',
            label: 'Control Name',
            priority: 1,
            position: 4,
            minWidth: 150,
            sortable: true,
            as: 'name'
        },
        {accessor: 'desc', label: 'Control Description', priority: 1, position: 5, minWidth: 180, sortable: true, as: 'description'},
        {accessor: 'frequency', label: 'Frequency', priority: 1, position: 6, minWidth: 120, sortable: true},
        {accessor: 'status', label: 'Approval Stage', priority: 2, position: 7, minWidth: 150, sortable: true, isHTML: true},
        {
            accessor: 'select',
            label: 'Select',
            sortable: false,
            position: 8,
            minWidth: 100,
            priority: 3,
            CustomComponent: ({row}) => <Switch onClick={() => onSelectRow(row)}/>
        }
    ];

    return (
        <Modal show={showModal} onHide={onClose} size={"xl"} centered>
            <Modal.Header className="px-3 pt-3 pb-0" closeButton>
                <Modal.Title className="my-0">Control Mapping</Modal.Title>
            </Modal.Header>
            <div
                className="row linking-existing-controls-modal__filters d-flex mt-1 justify-content-center justify-content-md-end map-controls-div">
                <div className="col-md-4 mx-1 mb-3">
                    <Select
                        className="react-select"
                        classNamePrefix="react-select"
                        defaultValue={{
                            label: "Select Standard",
                            value: -1,
                        }}
                        options={[
                            {label: "Select Standard", value: -1},
                            ...allStandards.map((s) => ({
                                label: s.name,
                                value: s.id,
                            })),
                        ]}
                        onChange={handleStandardChange}
                        isDisabled={allStandards.length === 0}
                    />
                </div>
                <div className="col-md-4 mx-1 mb-3">
                    <Select
                        className="react-select"
                        classNamePrefix="react-select"
                        defaultValue={{
                            label: "Select Project",
                            value: -1,
                        }}
                        ref={selectInputRef}
                        options={[
                            {label: "Select Project", value: -1},
                            ...projects.map((p) => ({
                                label: p.name,
                                value: p.id,
                            })),
                        ]}
                        onChange={handleProjectChange}
                        isDisabled={projects.length === 0}
                    />
                </div>
                <div className="col-md-2 mx-1 mb-3">
                    <button
                        name="search"
                        className="btn btn-primary"
                        onClick={handleSearch}
                    >
                        Search
                    </button>
                </div>
            </div>
            <Modal.Body className="p-3">
                <DataTable
                    columns={columns}
                    fetchUrl={route(
                        "compliance.project-controls.get-all-implemented-controls",
                        projectControl.id
                    )}
                    tag={`tasks-tab-${projectControl.id}`}
                    data={ajaxData}
                    refresh={refresh}
                    search
                    emptyString='No data found'
                />
            </Modal.Body>
        </Modal>
    );
};

export default ControlsModal;