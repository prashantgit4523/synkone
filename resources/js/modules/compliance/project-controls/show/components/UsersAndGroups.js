import React, { forwardRef,Fragment, useRef, useState,useImperativeHandle } from 'react';
import DataTable from '../../../../../common/custom-datatable/AppDataTable';
import Modal from 'react-bootstrap/Modal';
import {usePage } from '@inertiajs/inertia-react';
import ContentLoader from '../../../../../common/content-loader/ContentLoader';
import Spinner from 'react-bootstrap/Spinner';
import Button from 'react-bootstrap/Button';
import { Inertia } from '@inertiajs/inertia';
import fileDownload from "js-file-download";
import AddExistingUserModal from "./AddExistingUserModal";
import { useSelector,useDispatch } from "react-redux";
import route from "ziggy-js";
import { useDidMountEffect } from '../../../../../custom-hooks';
import Alert from "react-bootstrap/Alert";
import Papa from "papaparse";
import { storeGroupSelectData } from '../../../../../store/actions/native-awareness/groupSelectData';
import './stylesheet.scss';

function UsersAndGroups(props,ref) {
    const { ssoIsEnabled,projectId,controlId } = props;

    const addExistingUserModalRef = useRef();
    const [addGroupModelShow, setAddGroupModelShow] = useState(false);
    const [title, setTitle] = useState("Add New Group");
    const showContentLoader = false;
    const [groupUsers, setGroupUsers] = useState({ groupsData: [] });
    const [groupName, setGroupName] = useState("");
    const [groupId, setGroupId] = useState(0);
    const [groupNameError, setGroupNameError] = useState("");
    const [usersRequiredError, setUsersRequiredError] = useState("");
    const [showUserRequiredError, setShowUserRequiredError] = useState(false);
    const [groupsUserAddError, setGroupsUserAddError] = useState({ errors: [] });
    const appDataScope = useSelector(state => state.appDataScope.selectedDataScope.value);
    const [groupsRefresh, setGroupsRefresh] = useState(false);
    const [usersRefresh, setUsersRefresh] = useState(false);
    const [newUser, setNewUser] = useState({
        fname: '',
        lname: '',
        email: '',
        department: ''
    });
    const [groupNames, setGroupNames] = useState([]);
    useImperativeHandle(ref, () => ({
        newGroupModel,
      }));
    const [groupProcessing, setGroupProcessing] = useState(false);
    const [syncGroup, setSyncGroup] = useState(false);

    const { errors } = usePage().props;
    const dispatch = useDispatch();

    const newGroupModel = () => {
        getGroupNameList()
        importSystemUser()
        setTitle("Add New Group");
        if (groupId != 0) {
            //Reset user data
            let finalData = {
                groupsData: [],
            };
            setGroupUsers(finalData);
        }
        setGroupId(0);
        setGroupName("");
        setAddGroupModelShow(true);
    };

    const importSystemUser = () => {
        axiosFetch.get(route('policy-management.users-and-groups.import-system-users'),
        {
            params:{ 
                data_scope: appDataScope
            }
        })
    }

    const addUserToGroup = (e) => {
        e.preventDefault();
        let first_name = e.target[0].value;
        first_name = first_name[0].toUpperCase() + first_name.slice(1).toLowerCase();

        let last_name = e.target[1].value;
        last_name = last_name[0].toUpperCase() + last_name.slice(1).toLowerCase();

        const email = e.target[2].value;
        const department = e.target[3].value;
        let addErrors = {};
        let isError = 0;
        if (last_name === "") {
            addErrors = {
                ...addErrors,
                lname: "The Last Name field is required.",
            };
            isError = 1;
            setGroupsUserAddError({ addErrors });
        } else if (last_name.length < 2) {
            addErrors = {
                ...addErrors,
                lname: "The Last Name field must be at least 2 characters.",
            };
            isError = 1;
            setGroupsUserAddError({ addErrors });
        }
        if (first_name === "" || first_name.length < 2) {
            addErrors = {
                ...addErrors,
                fname: "First name length must be of 2 characters.",
            };
            isError = 1;
            setGroupsUserAddError({ addErrors });
        }
        if (
            /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/.test(
                email
            )
        ) {
            // email unique validation
            const index = groupUsers.groupsData.findIndex(
                (item) => item.user_email === email
            );
            if (index != null && index != undefined && index != -1) {
                addErrors = {
                    ...addErrors,
                    email: "User is already part of this group.",
                };
                isError = 1;
            }
            setGroupsUserAddError({ addErrors });
            let currentData = groupUsers.groupsData;
            if (isError == 0) {
                let userData = {
                    user_first_name: first_name,
                    user_last_name: last_name,
                    user_email: email,
                    user_department: department
                };
                currentData.push(userData);
                let finalData = {
                    groupsData: currentData,
                };
                setGroupUsers(finalData);
                e.target[0].value = "";
                e.target[1].value = "";
                e.target[2].value = "";
                e.target[3].value = "";
                setNewUser({ fname: "", lname: "", email: "", department: "" });
            }
        } else if (email === "") {
            addErrors = {
                ...addErrors,
                email: "The Email field is required.",
            };
            setGroupsUserAddError({ addErrors });
        }
        else {
            addErrors = {
                ...addErrors,
                email: "Enter a valid email address",
            };
            setGroupsUserAddError({ addErrors });
        }

    };

    //BULK IMPORT USERS
    const [bulkCSVErrors, setBulkCSVErrors] = useState("");

    const hiddenFileInput = React.useRef(null);

    const bulkImportUsers = () => {
        hiddenFileInput.current.click();
    };

    const bulkImportChange = async (event) => {
        setBulkCSVErrors("");
        const file = event.target.files[0];

        Papa.parse(file, {
            header: true,
            skipEmptyLines: true,
            complete: function (results) {
                uploadCSVData(results);
            },
            transformHeader: function (h) {
                return h.trim();
            },
            transform: function (v) {
                return v.trim();
            },
        })

        event.target.value = null; //Clearing the uploaded file
    };

    const uploadCSVData = ({ data: csvData, meta: { fields } }) => {
        if (!validateCSVData(fields, csvData)) {
            return false;
        }

        let currentData = groupUsers.groupsData;

        csvData.map((row) => {
            let usersData = {
                user_first_name: row.first_name[0].toUpperCase() + row.first_name.slice(1).toLowerCase(),
                user_last_name: row.last_name[0].toUpperCase() + row.last_name.slice(1).toLowerCase(),
                user_email: row.email,
                user_department: row.department,
            };
            const index = groupUsers.groupsData.findIndex(
                (item) => item.user_email === usersData.user_email
            );
            if (index != null && index != undefined && index != -1) {
                setBulkCSVErrors("A user in the CSV is already listed below.");
            } else {
                currentData.push(usersData);
                let finalData = {
                    groupsData: currentData,
                };
                setGroupUsers(finalData);
            }
        });
    };

    const validateCSVData = (fields, csvData) => {
        /* empty csv case */
        if (fields.length == 0) {
            setBulkCSVErrors("CSV file is empty");
            return false;
        }

        /* Handling the header farmat */
        if (
            fields[0] != "first_name" ||
            fields[1] != "last_name" ||
            fields[2] != "email" || fields[3] != "department"
        ) {
            setBulkCSVErrors("CSV Header format is invalid");
            return false;
        }


        //CSV Data Validation
        if (csvData.length == 0) {
            setBulkCSVErrors("The CSV file is incomplete.");
            return false;
        }


        //Checking for empty columns
        for (const row of csvData) {
            if (!row.hasOwnProperty('first_name') || !row.hasOwnProperty('last_name') || !row.hasOwnProperty('email') || !row.hasOwnProperty('department')) {
                setBulkCSVErrors("The CSV file is incomplete.");
                return false;
            }
        }

        return true
    }

    const downloadCSVTemplate = async () => {
        try {
            let response = await axiosFetch({
                url: route(
                    "policy-management.users-and-groups.users.download-csv-template"
                ),
                method: "GET",
                responseType: "blob", // Important
            });

            fileDownload(response.data, "user-template.csv");
        } catch (error) {
            console.log(error);
        }
    };

    const removeThis = (rowData) => {
        const index = groupUsers.groupsData.findIndex(
            (item) => item.user_email === rowData.user_email
        );
        if (index != null && index != undefined) {
            groupUsers.groupsData.splice(index, 1);
        }
        let finalData = {
            groupsData: groupUsers.groupsData,
        };
        setGroupUsers(finalData);
    };

    const getGroupNameList = async () => {
        await axiosFetch.get(route('policy-management.users-and-groups.groups.get-group-name-list')
        ).then((res) => {
            setGroupNames(res.data)
        });
    }

    const saveGroup = () => {
        if (groupName.length == 0) {
            setGroupNameError("The Group Name field is required.");
        }
        else if(groupNames.some((g) => g.toLowerCase() == groupName.toLowerCase())){
            setGroupNameError("The name has already been taken");
        } 
        else if (groupName.length <= 2) {
            setGroupNameError(
                "The Group name length must be greater than 2 character."
            );
        } else if (groupName.length > 2) {
            setGroupNameError("");
            // proceed futher for saving group data
            setGroupProcessing(true);
            let postData = {
                name: groupName,
                users: groupUsers,
                data_scope: appDataScope,
            };
            Inertia.post(route('policy-management.users-and-groups.groups.store'), postData, {
                preserveState: true,
                onSuccess: () => {
                    setAjaxDataGroup([]);
                    handleAddGroupModalClose();
                    setUsersRefresh(true);
                },
                onError: (res) => {
                    if (res.usersRequired) {
                        setUsersRequiredError(res.usersRequired);
                        setShowUserRequiredError(true);
                    }
                },
                onFinish: () => {
                    dispatch(storeGroupSelectData(groupName));
                    setGroupProcessing(false);
                    Inertia.visit(
                        route('compliance-project-control-show', [projectId, controlId, 'tasks'])
                    );
                }
            });
        }
    }

    const syncSSOUsers = () => {
        setSyncGroup(true)
        Inertia.get(route("policy-management.users-and-groups.sync-sso-users"),
            {
                data_scope: appDataScope,
                onFinish: () => {
                    setSyncGroup(false)
                }
            });
        dispatch(storeGroupSelectData("All SSO users"));
    }

    const addUserColumns = [
        {
            accessor: "user_first_name",
            label: "First Name",
            priorityLevel: 1,
            position: 1,
            minWidth: 150,
            sortable: false,
        },
        {
            accessor: "user_last_name",
            label: "Last Name",
            priorityLevel: 1,
            position: 2,
            minWidth: 150,
            sortable: false,
        },
        {
            accessor: "user_email",
            label: "Email",
            priorityLevel: 1,
            position: 3,
            minWidth: 50,
            sortable: false,
        },
        {
            accessor: "user_department",
            label: "Department",
            priorityLevel: 1,
            position: 4,
            minWidth: 50,
            sortable: false,
        },
        {
            accessor: "",
            label: "Action",
            priorityLevel: 2,
            position: 7,
            minWidth: 150,
            width: 300,
            sortable: false,
            CustomComponent: ({ row }) => {
                return (
                    <Fragment>
                        <span
                            className="btn btn-danger btn-xs waves-effect waves-light delete"
                            onClick={() => removeThis(row)}
                        >
                            <i className="fe-trash-2"></i>
                        </span>
                    </Fragment>
                );
            },
        },
    ];

    const handleAddGroupModalClose = () => {
        setBulkCSVErrors("");
        setGroupsUserAddError("");
        setGroupNameError("");
        setShowUserRequiredError(false);
        setGroupUsers({ groupsData: [] });
        addExistingUserModalRef.current.clearAllStates();
        setAddGroupModelShow(false);
        if (errors.name)
            errors.name = '';
    }

    const updateGroupName = (e) => {
        setGroupName(e.target.value);
        setGroupNameError('');
        if (errors.name)
            errors.name = '';
    }

    useDidMountEffect(() => {
        setGroupsRefresh(!groupsRefresh);
        setUsersRefresh(!usersRefresh);
    }, [appDataScope]);

    const colVal = ssoIsEnabled ? "col-lg-3 d-grid" : "col-lg-4 d-grid";

    return (
        <Fragment>
            <ContentLoader show={showContentLoader}>
                <Modal
                    show={addGroupModelShow}
                    onHide={handleAddGroupModalClose}
                    aria-labelledby="example-custom-modal-styling-title"
                    size="xl"
                >
                    <Modal.Header className='px-3 pt-3 pb-0' closeButton>
                        <Modal.Title className='my-0' id="example-custom-modal-styling-title">
                            {title}
                        </Modal.Title>
                    </Modal.Header>
                    <Modal.Body className='p-3'>
                        <div className="row">
                            <Alert variant="danger" show={showUserRequiredError} onClose={() => setShowUserRequiredError(false)} dismissible>
                                <strong>{usersRequiredError}</strong>
                            </Alert>
                            <div className="col-lg-12">
                                <div className="mb-3">
                                    <label htmlFor="group-name" className="form-label">Name <span
                                        className="required text-danger">*</span></label>
                                    <input type="text" className="form-control" name="name" id="group-name"
                                        value={groupName} onChange={(e) => {
                                            updateGroupName(e)
                                        }} placeholder="Group name" />
                                    <input type="text" className="form-control" name="id" id="group-id"
                                        value={groupId} onChange={(e) => {
                                            setGroupId(e.target.value)
                                        }} placeholder="Group id" hidden />
                                    {(groupNameError) &&
                                        <span className="invalid-feedback d-block">{groupNameError}</span>}
                                    {(errors.name) &&
                                        <span className="invalid-feedback d-block">{errors.name}</span>}
                                </div>
                            </div>
                        </div>
                        <div className="row mb-3 mt-1">
                            {bulkCSVErrors && (
                                <span className="invalid-feedback d-block">
                                    {bulkCSVErrors}
                                </span>
                            )}
                            <input
                                type="file"
                                ref={hiddenFileInput}
                                onChange={bulkImportChange}
                                name="users-bulk-import"
                                className="d-none"
                                id="users-bulk-import"
                            />
                            <div className={colVal}>
                                <button className="btn btn-danger width-xl waves-effect waves-light ms-1"
                                    type="button"
                                    onClick={bulkImportUsers}
                                    id="users-bulk-import-btn"
                                >
                                    Bulk Import Users
                                </button>
                            </div>
                            <div className={colVal}>
                                <button
                                    type="button"
                                    onClick={downloadCSVTemplate}
                                    className="btn btn-outline-secondary width-xl waves-effect waves-light ms-1 mt-2 mt-lg-0"
                                >
                                    Download CSV Template
                                </button>
                            </div>
                            <div className={colVal}>
                                <button
                                    type="button"
                                    id="add-existing-users-to-group-btn"
                                    className="btn btn-outline-secondary width-xl waves-effect waves-light ms-1 mt-2 mt-lg-0"
                                    onClick={() =>
                                        addExistingUserModalRef.current.addExistingUser()
                                    }
                                >
                                    Import System Users
                                </button>
                            </div>
                            
                            <div className="col-lg-3 d-grid">
                            {ssoIsEnabled && 
                                <>
                                    {!syncGroup ? 
                                    <button
                                        type="button"
                                        className="btn btn-primary waves-effect waves-light float-end"
                                        style={{marginRight:"5px"}}
                                        onClick={() => syncSSOUsers()}
                                    >
                                        <i className="mdi mdi-sync"/> Sync SSO Users
                                    </button> :
                                        <Button variant="primary" disabled>
                                        <Spinner animation="border" size="sm" /> Fetching...</Button>
                                    }
                                </>
                            }
                            </div>
                        </div>
                        <form
                            id="add-user-form"
                            className="absolute-error-form"
                            onSubmit={addUserToGroup}
                        >
                            <div className="row style-container__user">
                                <div className="col-lg-3">
                                    <div className="mb-3">
                                        <input
                                            type="text"
                                            className="form-control"
                                            name="first_name"
                                            placeholder="First Name"
                                            value={newUser.fname}
                                            onChange={(e) =>
                                                setNewUser({
                                                    ...newUser,
                                                    fname: e.target
                                                        .value,
                                                })
                                            }
                                        />
                                        {groupsUserAddError.errors &&
                                            (newUser.fname === "" ||
                                                newUser.fname.length <
                                                2) ? (
                                            <span className="invalid-feedback d-block">
                                                {
                                                    groupsUserAddError
                                                        .errors.fname
                                                }
                                            </span>
                                        ) : (
                                            ""
                                        )}
                                    </div>
                                </div>
                                <div className="col-lg-3">
                                    <div className="mb-3">
                                        <input
                                            type="text"
                                            className="form-control"
                                            name="last_name"
                                            placeholder="Last Name"
                                            value={newUser.lname}
                                            onChange={(e) =>
                                                setNewUser({
                                                    ...newUser,
                                                    lname: e.target
                                                        .value,
                                                })
                                            }
                                        />
                                        {groupsUserAddError.errors &&
                                            (newUser.lname === "" ||
                                                newUser.lname.length <
                                                2) ? (
                                            <span className="invalid-feedback d-block">
                                                {
                                                    groupsUserAddError
                                                        .errors.lname
                                                }
                                            </span>
                                        ) : (
                                            ""
                                        )}
                                    </div>
                                </div>
                                <div className="col-lg-3">
                                    <div className="mb-3">
                                        <input
                                            type="text"
                                            className="form-control"
                                            name="email"
                                            placeholder="Email Address"
                                            value={newUser.email}
                                            onChange={(e) =>
                                                setNewUser({
                                                    ...newUser,
                                                    email: e.target
                                                        .value,
                                                })
                                            }
                                        />
                                        {groupsUserAddError.errors && (!/^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/.test(newUser.email)) &&
                                            <span className="invalid-feedback d-block">
                                                {
                                                    groupsUserAddError
                                                        .errors.email ? groupsUserAddError
                                                            .errors.email : ""
                                                }
                                            </span>
                                        }
                                    </div>
                                </div>
                                <div className="col-lg-2">
                                    <div className="mb-3">
                                        <input
                                            type="text"
                                            className="form-control"
                                            name="department"
                                            placeholder="Department"
                                            value={newUser.department}
                                            onChange={(e) =>
                                                setNewUser({
                                                    ...newUser,
                                                    department: e.target
                                                        .value,
                                                })
                                            }
                                        />
                                        {groupsUserAddError.errors && newUser.department.length < 2 &&
                                            <span className="invalid-feedback d-block">
                                                {
                                                    groupsUserAddError
                                                        .errors.department
                                                }
                                            </span>
                                        }
                                    </div>
                                </div>
                                <div className="col-lg-1">
                                    <div className="mb-3">
                                        <button
                                            type="submit"
                                            className="btn btn-danger waves-effect waves-light"
                                        >
                                            Add
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <div className="table-container">
                            <DataTable
                                columns={addUserColumns}
                                rows={groupUsers.groupsData}
                                tag="project-groups-data-offline"
                                refresh={groupUsers}
                                offlineMode
                                search
                                emptyString='No data found'
                            />
                        </div>
                    </Modal.Body>
                    <Modal.Footer className='px-3 pt-0 pb-3'>
                        <button type="button" className="btn btn-secondary waves-effect" data-dismiss="modal"
                            onClick={handleAddGroupModalClose}>Close
                        </button>
                        {!groupProcessing ?
                            <button type="button" className="btn btn-primary waves-effect waves-light"
                                id="submit-group-btn"
                                onClick={() =>  saveGroup() }>Save Changes
                            </button> :
                            <Button variant="primary" disabled>
                                <Spinner animation="border" size="sm" /> Updating...</Button>
                        }
                    </Modal.Footer>
                </Modal>

                {/* Modal for add existing user */}
                <AddExistingUserModal
                    ref={addExistingUserModalRef}
                    groupUsers={groupUsers}
                    actionFunction={(userData) => {
                        setGroupUsers(userData);
                    }}
                />
            </ContentLoader>
        </Fragment>
    );
}

export default forwardRef(UsersAndGroups);