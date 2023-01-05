import React, {Fragment, useEffect, useRef, useState} from 'react';
import BreadcumbsComponent from '../../../common/breadcumb/Breadcumb';
import AppLayout from '../../../layouts/app-layout/AppLayout';
import Tabs from 'react-bootstrap/Tabs';
import Tab from 'react-bootstrap/Tab';
import DataTable from '../../../common/custom-datatable/AppDataTable';
import Modal from 'react-bootstrap/Modal';
import Dropdown from 'react-bootstrap/Dropdown';
import {Link, useForm, usePage} from '@inertiajs/inertia-react';
import ContentLoader from '../../../common/content-loader/ContentLoader';
import Spinner from 'react-bootstrap/Spinner';
import Button from 'react-bootstrap/Button';
import './stylesheet.scss';
import {Inertia} from '@inertiajs/inertia';
import FlashMessages from "../../../common/FlashMessages";
import fileDownload from "js-file-download";
import AddExistingUserModal from "./components/AddExistingUserModal";
import {useSelector} from "react-redux";
import route from "ziggy-js";
import {useStateIfMounted} from "use-state-if-mounted"
import {useDidMountEffect} from '../../../custom-hooks';
import Alert from "react-bootstrap/Alert";
import Papa from "papaparse";
import CustomDropdown from "../../../common/custom-dropdown/CustomDropdown";

function UsersAndGroups(props) {
    const [activeKey, setActiveKey] = useState("groups");
    const {activeTab} = props;
    const [errorFormId,setErrorFormId] = useState(0);

    useEffect(() => {
        document.title = "Users & Groups";
        if (activeTab) {
            setAjaxData({});
            return setActiveKey(activeTab);
        }
    }, [activeTab]);

    const addExistingUserModalRef = useRef();
    const [addGroupModelShow, setAddGroupModelShow] = useState(false);
    const [title, setTitle] = useState("Add New Group");
    const [userModelShow, setUserModelShow] = useState(false);
    const [showContentLoader, setShowContentLoader] = useStateIfMounted(false);
    const [ajaxData, setAjaxData] = useStateIfMounted({});
    const [ajaxDataGroup, setAjaxDataGroup] = useStateIfMounted({});
    const [groupUsers, setGroupUsers] = useState({groupsData: []});
    const [groupName, setGroupName] = useState("");
    const [groupId, setGroupId] = useState(0);
    const [groupNameError, setGroupNameError] = useState("");
    const [usersRequiredError, setUsersRequiredError] = useState("");
    const [showUserRequiredError,setShowUserRequiredError] = useState(false);
    const [groupsUserAddError, setGroupsUserAddError] = useState({errors: []});
    const appDataScope = useSelector(state => state.appDataScope.selectedDataScope.value);
    const [groupsRefresh, setGroupsRefresh] = useState(false);
    const [usersRefresh, setUsersRefresh] = useState(false);
    const [ssoUsersSyncing, setSsoUsersSyncing] = useState(false);

    const [newUser, setNewUser] = useState({
        fname: '',
        lname: '',
        email: '',
        department: ''
    });
    const [groupProcessing, setGroupProcessing] = useState(false);
    const {errors, ssoIsEnabled} = usePage().props;
    const {data, setData, processing} = useForm({
        id: 0,
        first_name: "",
        last_name: "",
        email: "",
        department: ""
    });

    const handleUserEdit = (row) => {
        setData({
            id: row[0],
            first_name: row[1],
            last_name: row[2],
            email: row[3],
            department: row[4],
        });
        setUserModelShow(true);
    };

    const handleUserDelete = (e, row) => {
        e.preventDefault();
        AlertBox(
            {
                title: "Are you sure?",
                text: "You will not be able to recover this user!",
                showCancelButton: true,
                confirmButtonColor: "#ff0000",
                confirmButtonText: "Yes, delete it!",
                icon: "warning",
                iconColor:'#ff0000',
            },
            function (result) {
                if (result.isConfirmed) {
                    Inertia.post(
                        route(
                            "policy-management.users-and-groups.users.delete-user",
                            row[0]
                        ),
                        {
                            data_scope: appDataScope,
                            _method: 'DELETE'
                        },
                        {
                            preserveScroll: true,
                            onSuccess: () => {
                                setAjaxData({});
                                refreshTables();
                            },
                        }
                    );
                }
            }
        );
    };

    const handleUserActive = (e, row) => {
        e.preventDefault();

        Inertia.get(
            route("policy-management.users-and-groups.users.activate", row[0]),
            {
                data_scope: appDataScope,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAjaxData({});
                },
            }
        );
    };

    const editGroup = async (e, row) => {
        e.preventDefault();
        const editGroupId = row[5];
        axiosFetch
            .get(
                route("policy-management.users-and-groups.groups.edit", editGroupId)
            )
            .then((res) => {
                setTitle("Update Group");
                setGroupName(res.data.group.name);
                setGroupId(res.data.group.id);
                var users = res.data.users;
                var editUserData = [];
                users.forEach((element) => {
                    const tempUserData = {
                        user_first_name: element.first_name,
                        user_last_name: element.last_name,
                        user_email: element.email,
                        user_department: element.department,
                    };
                    editUserData.push(tempUserData);
                });
                var finalData = {
                    groupsData: editUserData,
                };
                setGroupUsers(finalData);
                setAddGroupModelShow(true);
                addExistingUserModalRef.current.checkSelectedUsers();
            });
    };

    const deleteGroup = (e, row) => {
        e.preventDefault();
        
        AlertBox(
            {
                title: "Are you sure?",
                text: "You will not be able to recover this group!",
                showCancelButton: true,
                confirmButtonColor: "#ff0000",
                confirmButtonText: "Yes, delete it!",
                icon: "warning",
                iconColor:'#ff0000',
            },
            function (confirmed) {
                if (confirmed.value) {
                    Inertia.delete(
                        route(
                            "policy-management.users-and-groups.groups.delete",
                            row[5]
                        ),
                        {
                            data: {
                                data_scope: appDataScope,
                            },
                            preserveScroll: true,
                            onSuccess: () => {
                                setAjaxDataGroup({});
                                refreshTables();
                            },
                        }
                    );
                }
            }
        );
    };

    const newGroupModel = () => {
        importSystemUser()
        setTitle("Add New Group");
        if (groupId != 0) {
            //Reset user data
            var finalData = {
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

    function editUserSubmit(event) {
        event.preventDefault();
        Inertia.post(
            route("policy-management.users-and-groups.users.update", data.id),
            {
                ...data,
                data_scope: appDataScope,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAjaxData({});
                    setUserModelShow(false);
                    setErrorFormId(0);
                },
                onFinish: () => {
                    refreshTables();
                }
            }
        );
        setTimeout(() => {
            setErrorFormId(data.id); 
        }, 2000);
    }

    const addUserToGroup = (e) => {
        e.preventDefault();
        setGroupsUserAddError({errors: []});
        let first_name = e.target[0].value;
        let last_name = e.target[1].value; 
        const email = e.target[2].value;
        const department = e.target[3].value;
        var errors = {};
        var isError = 0;
        if (last_name === "") {
            errors = {
                ...errors,
                lname: "The Last Name field is required.",
            };
            isError = 1;
            setGroupsUserAddError({errors});
        }else if(last_name.length < 2){
            errors = {
                ...errors,
                lname: "The Last Name field must be at least 2 characters.",
            };
            isError = 1;
            setGroupsUserAddError({errors});
        }
        if (first_name === "") {
            errors = {
                ...errors,
                fname: "The First Name field is required.",
            };
            isError = 1;
            setGroupsUserAddError({errors});
        }
        else if(first_name.length < 2){
            errors = {
                ...errors,
                fname: "The First Name field must be at least 2 characters.",
            };
            isError = 1;
            setGroupsUserAddError({errors});
        }
        if (/^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/.test(email)) {
            // email unique validation
            const index = groupUsers.groupsData.findIndex(
                (item) => item.user_email === email
            );
            if (index != null && index != undefined && index != -1) {
                errors = {
                    ...errors,
                    email: "User is already part of this group.",
                };
                setGroupsUserAddError({errors});
                isError = 1;
            }
            var currentData = groupUsers.groupsData;
            if (isError == 0) {
                first_name = first_name[0].toUpperCase() + first_name.slice(1).toLowerCase();
                last_name = last_name[0].toUpperCase() + last_name.slice(1).toLowerCase();
                var userData = {
                    user_first_name: first_name,
                    user_last_name: last_name,
                    user_email: email,
                    user_department: department
                };
                currentData.push(userData);
                var finalData = {
                    groupsData: currentData,
                };
                setGroupUsers(finalData);
                e.target[0].value = "";
                e.target[1].value = "";
                e.target[2].value = "";
                e.target[3].value = "";
                setNewUser({fname: "", lname: "", email: "",department:""});
            }
        }else if(email === ""){
            errors = {
                ...errors,
                email: "The Email field is required.",
            };
            setGroupsUserAddError({errors});
        } 
        else {
            errors = {
                ...errors,
                email: "Enter a valid email address.",
            };
            setGroupsUserAddError({errors});
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
            complete: function(results) {
              uploadCSVData(results);
            },
            transformHeader: function(h) {
                return h.trim();
            },
            transform: function(v) {
                return v.trim();
            },
        })

        event.target.value = null; //Clearing the uploaded file
    };

    const uploadCSVData = ({data: csvData, meta: {fields}}) => {
        if(!validateCSVData(fields, csvData)){
            return false;
        }

        var currentData = groupUsers.groupsData;

        csvData.map((row) => {
            var usersData = {
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
                var finalData = {
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
        var finalData = {
            groupsData: groupUsers.groupsData,
        };
        setGroupUsers(finalData);
    };

    const saveGroup = () => {
        if (groupName.length == 0) {
            setGroupNameError("The Group Name field is required.");
        } else if (groupName.length <= 2) {
            setGroupNameError(
                "The Group name length must be greater than 2 character."
            );
        } else if (groupName.length > 2) {
            setGroupNameError("");
            // proceed futher for saving group data
            setGroupProcessing(true);
            var postData = {
                name: groupName,
                users: groupUsers,
                data_scope: appDataScope,
            };
            Inertia.post('users-and-groups/groups/store', postData, {
                preserveState: true,
                onSuccess: () => {
                    setAjaxDataGroup({});
                    handleAddGroupModalClose();
                    refreshTables();
                },
                onError: (res) => {
                    if(res.usersRequired){
                        setUsersRequiredError(res.usersRequired);
                        setShowUserRequiredError(true);
                    }
                },
                onFinish: () => {
                    setGroupProcessing(false);
                }
            });
        }
    }

    const updateGroup = () => {
        if (groupName.length === 0) {
            setGroupNameError("The Group Name field is required.");
        } else if (groupName.length <= 2) {
            setGroupNameError(
                "The Group name length must be greater than 2 character."
            );
        } else if (groupName.length > 2) {
            setGroupProcessing(true);
            setGroupNameError("");
            // proceed futher for saving group data
            var postData = {
                id: groupId,
                name: groupName,
                users: groupUsers,
                data_scope: appDataScope,
            };

            Inertia.post(
                route(
                    "policy-management.users-and-groups.groups.update",
                    groupId
                ),
                postData,
                {
                    preserveState: true,
                    onSuccess: () => {
                        setAjaxDataGroup({});
                        setAddGroupModelShow(false);
                        refreshTables();
                    },
                    onFinish: () => {
                        setGroupProcessing(false);
                    }
                }
            );
        }
    };

    const syncSSOUsers = () => {
        setSsoUsersSyncing(true);
        Inertia.get(route("policy-management.users-and-groups.sync-sso-users"),
        {
            data_scope: appDataScope,
            onFinish: () => {
                setSsoUsersSyncing(false);
            }
        });
    }

    const refreshTables = () => {
        setUsersRefresh(!usersRefresh);
        setGroupsRefresh(!groupsRefresh);
    }

    const breadcumbsData = {
        title: "Users & Groups - Policy Management",
        breadcumbs: [
            {
                title: "Policy Management",
                href: "campaigns",
            },
            {
                title: "Users & Groups",
                href: "",
            },
        ],
    };

    const fetchURL = "policy-management/users-and-groups/groups/get-json-data";
    const columns = [
        {
            accessor: "0",
            label: "Name",
            priority: 3,
            position: 1,
            minWidth: 140,
            as: 'name',
            sortable: true
        },
        {
            accessor: "1",
            label: "Status",
            priority: 2,
            position: 2,
            isHTML: true,
            minWidth: 70,
            as: 'status',
            sortable: true
        },
        {
            accessor: "2",
            label: "No. of Members",
            priority: 2,
            position: 3,
            minWidth: 120,
            sortable: true,
            as: 'users_count'
        },
        {
            accessor: "3",
            label: "Date Created",
            priority: 2,
            position: 4,
            minWidth: 140,
            as: 'created_at',
            sortable: true
        },
        {
            accessor: "4",
            label: "Last Updated",
            priority: 1,
            position: 5,
            minWidth: 140,
            as: 'updated_at',
            sortable: true
        },
        {
            accessor: "5",
            label: "Action",
            priority: 4,
            position: 6,
            minWidth: 50,
            sortable: false,
            CustomComponent: ({row}) => {
                return (
                    <
                        CustomDropdown
                        button={<i className="mdi mdi-dots-horizontal m-0 text-muted h3" />}
                        dropdownItems={
                            <>
                                <button
                                    className="dropdown-item d-flex align-items-center"
                                    onClick={(e) => editGroup(e, row)}
                                >
                                    <i className="mdi mdi-square-edit-outline font-18 me-1"/> Edit
                                </button>
                                <button
                                    className="dropdown-item d-flex align-items-center"
                                    onClick={(e) => deleteGroup(e, row)}
                                >
                                    <i className="mdi mdi-delete-outline font-18 me-1"/> Delete
                                </button>
                            </>
                        }
                    />
                );
            },
        },
    ];

    const fetchURLUsers = "policy-management/users-and-groups/users/get-data";
    const columnsUsers = [
        {
            accessor: "1",
            label: "First Name",
            priority: 1,
            position: 1,
            minWidth: 140,
            as: 'first_name',
            sortable: true
        },
        {
            accessor: "2",
            label: "Last Name",
            priority: 2,
            position: 2,
            minWidth: 140,
            as: 'last_name',
            sortable: true
        },
        {
            accessor: "3",
            label: "Email",
            priority: 3,
            position: 3,
            width: 200,
            minWidth: 160,
            as: 'email',
            sortable: true
        },
        {
            accessor: "4",
            label: "Department",
            priority: 2,
            position: 4,
            minWidth: 80,
            as: 'department',
            sortable: true
        },
        {
            accessor: "5",
            label: "Status",
            priority: 3,
            isHTML: true,
            position: 5,
            minWidth: 70,
            as: 'status',
            sortable: true
        },
        {
            accessor: "6",
            label: "Date Created",
            priority: 1,
            position: 6,
            minWidth: 140,
            as: 'created_at',
            sortable: true
        },
        {
            accessor: "7",
            label: "Last Updated",
            priority: 1,
            position: 7,
            minWidth: 140,
            as: 'updated_at',
            sortable: true
        },
        {
            accessor: "action",
            label: "Action",
            priority: 4,
            position: 8,
            minWidth: 50,
            sortable: false,
            CustomComponent: ({row}) => {
                return (
                    <
                        CustomDropdown
                        button={<i className="mdi mdi-dots-horizontal m-0 text-muted h3" />}
                        dropdownItems={
                            row[8] == "active" ? (
                                    <>
                                        <button
                                            className="dropdown-item d-flex align-items-center"
                                            onClick={() => handleUserEdit(row)}
                                        >
                                            <i className="mdi mdi-pencil me-2 text-muted font-18 vertical-middle"></i>
                                            Edit User
                                        </button>
                                        <button
                                            className="dropdown-item d-flex align-items-center"
                                            onClick={(e) =>
                                                handleUserDelete(e, row)
                                            }
                                        >
                                            <i className="mdi mdi-delete-forever me-2 text-muted font-18 vertical-middle"></i>
                                            Delete
                                        </button>
                                    </>
                                ) : (
                                    <>
                                        <button
                                            className="dropdown-item d-flex align-items-center"
                                            onClick={() => handleUserEdit(row)}
                                        >
                                            <i className="mdi mdi-pencil me-2 text-muted font-18 vertical-middle" />
                                            Edit User
                                        </button>
                                        <button
                                            className="dropdown-item d-flex align-items-center"
                                            onClick={(e) =>
                                                handleUserDelete(e, row)
                                            }
                                        >
                                            <i className="mdi mdi-delete-forever me-2 text-muted font-18 vertical-middle" />
                                            Delete
                                        </button>
                                        <button
                                            className="dropdown-item d-flex align-items-center"
                                            onClick={(e) =>
                                                handleUserActive(e, row)
                                            }
                                        >
                                            <i className="mdi mdi-account-check me-2 text-muted font-18 vertical-middle" />
                                            Activate
                                        </button>
                                    </>
                            )
                        }
                    />
                );
            },
        },
    ];

    const addUserColumns = [
        {
            accessor: "user_first_name",
            label: "First Name",
            priority: 1,
            position: 1,
            minWidth: 150,
            sortable: true,
        },
        {
            accessor: "user_last_name",
            label: "Last Name",
            priority: 1,
            position: 2,
            minWidth: 150,
            sortable: true,
        },
        {
            accessor: "user_email",
            label: "Email",
            priority: 1,
            position: 3,
            minWidth: 50,
            sortable: true,
        },
        {
            accessor: "user_department",
            label: "Department",
            priority: 1,
            position: 4,
            minWidth: 50,
            sortable: true,
        },
        {
            accessor: "",
            label: "Action",
            priority: 2,
            position: 7,
            minWidth: 150,
            width: 300,
            sortable: false,
            CustomComponent: ({row}) => {
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
        setGroupUsers({groupsData: []});
        addExistingUserModalRef.current.clearAllStates();
        setAddGroupModelShow(false);
        if (errors.name)
            errors.name = '';
    }

    const handleAddGroupModalShow = () => false;

    const updateGroupName = (e) => {
        setGroupName(e.target.value);
        setGroupNameError('');
        if (errors.name)
            errors.name = '';
    }

    useDidMountEffect(() => {
        refreshTables();
    }, [appDataScope]);

    const [emailValidationError,setEmailValidationError] = useState('');

    useEffect(()=>{
        setEmailValidationError('')
        const index = groupUsers.groupsData.findIndex(
            (item) => item?.user_email === newUser?.email
        );
        if (index != null && index != undefined && index != -1) {
            setEmailValidationError('User is already part of this group.')
        }
        if(groupsUserAddError.errors?.email ==='Enter a valid email address.'){
            /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/.test(newUser?.email) ?
                setEmailValidationError('') : setEmailValidationError(groupsUserAddError.errors.email)
        }  
        if(groupsUserAddError.errors?.email ==='The Email field is required.' && newUser?.email.length === 0){
            setEmailValidationError('The Email field is required.')
        }
    },[newUser?.email,groupsUserAddError.errors?.email]);

    return (
        <AppLayout>
            <Fragment>
                <ContentLoader show={showContentLoader}>
                    <BreadcumbsComponent data={breadcumbsData}/>
                    {Object.keys(errors).length < 1 && <FlashMessages/>}
                    <div className='row'>
                        <div className='col'>
                            <div className='card'>
                                <div className='card-body'>
                                    <div className="row">
                                        <div className="col-12">
                                            <button
                                                type="button"
                                                id="add-new-group-btn"
                                                className="btn btn-sm btn-primary waves-effect waves-light float-end"
                                                onClick={newGroupModel}
                                            >
                                                <i className="mdi mdi-plus-circle"/> New Group
                                            </button>
                                        {ssoIsEnabled && (!ssoUsersSyncing ? <button
                                                type="button"
                                                id="add-new-group-btn"
                                                className="btn btn-sm btn-primary waves-effect waves-light float-end"
                                                style={{marginRight:"5px"}}
                                                onClick={() => syncSSOUsers()}
                                            >
                                                <i className="mdi mdi-sync"/> Sync SSO Users
                                            </button> :
                                            <Button variant="primary" disabled
                                            className="btn btn-sm btn-primary waves-effect waves-light float-end" style={{marginRight:"5px"}}>
                                                <Spinner animation="border" size="sm" /> Fetching...
                                            </Button>
                                            )}
                                        </div>
                                        <div
                                            className="col-12"
                                            id="users-and-groups-tabs-section"
                                        >
                                            <Tabs
                                                activeKey={activeKey}
                                                onSelect={(key) => setActiveKey(key)}
                                                className="mb-3"
                                            >
                                                <Tab eventKey="groups" title="Groups">
                                                    <DataTable
                                                        columns={columns}
                                                        fetchUrl={fetchURL}
                                                        data={ajaxDataGroup}
                                                        refresh={groupsRefresh}
                                                        tag="users-and-groups-groups"
                                                        search
                                                        emptyString="No data found"
                                                    />
                                                </Tab>
                                                <Tab eventKey="users" title="Users">
                                                    <div>
                                                        <DataTable
                                                            search
                                                            columns={columnsUsers}
                                                            fetchUrl={fetchURLUsers}
                                                            data={ajaxData}
                                                            refresh={usersRefresh}
                                                            tag="users-and-groups-users"
                                                            emptyString="No data found"
                                                        />
                                                    </div>
                                                </Tab>
                                            </Tabs>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <Modal
                        show={addGroupModelShow}
                        onHide={handleAddGroupModalClose}
                        onShow={handleAddGroupModalShow}
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
                                        }} placeholder="Group name"/>
                                        <input type="text" className="form-control" name="id" id="group-id"
                                               value={groupId} onChange={(e) => {
                                            setGroupId(e.target.value)
                                        }} placeholder="Group id" hidden/>
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
                                    <div className="col-lg-4 d-grid">
                                    <button className="btn btn-danger width-xl waves-effect waves-light ms-1"
                                        type="button"
                                        onClick={bulkImportUsers}
                                        id="users-bulk-import-btn"
                                    >
                                        Bulk Import Users
                                    </button>
                                    </div>
                                    <div className="col-lg-4 d-grid">
                                    <button
                                        type="button"
                                        onClick={downloadCSVTemplate}
                                        className="btn btn-outline-secondary width-xl waves-effect waves-light ms-1 mt-2 mt-lg-0"
                                    >
                                        Download CSV Template
                                    </button>
                                    </div>
                                    <div className="col-lg-4 d-grid">
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
                                           {emailValidationError &&
                                                <span className="invalid-feedback d-block">
                                                    {emailValidationError}
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
                                    refresh={groupUsers}
                                    tag="groups-data-offline"
                                    offlineMode
                                    search
                                    resetOnExit
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
                                        onClick={() => groupId == 0 ? saveGroup() : updateGroup()}>Save Changes
                                </button> :
                                <Button variant="primary" disabled>
                                    <Spinner animation="border" size="sm"/> Updating...</Button>
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

                    {/* Modal for user edit form */}
                    <Modal
                        show={userModelShow}
                        onHide={() => {
                            setUserModelShow(false);
                            setErrorFormId(0);
                        }}
                        aria-labelledby="example-custom-modal-styling-title"
                        size="lg"
                    >
                        <Modal.Header className='px-3 pt-3 pb-0' closeButton>
                            <Modal.Title className='my-0' id="example-custom-modal-styling-title">
                                Edit User
                            </Modal.Title>
                        </Modal.Header>
                        <form
                            id="update-users-form"
                            onSubmit={editUserSubmit}
                        >
                            <Modal.Body className='p-3'>
                                <div className="row">
                                    <div className="col-lg-6">
                                        <div className="mb-3">
                                            <label
                                                htmlFor="first-name"
                                                className="form-label"
                                            >
                                                First Name{" "}
                                            </label>
                                            <input
                                                type="text"
                                                className="form-control"
                                                name="first_name"
                                                id="first-name"
                                                value={data.first_name}
                                                onChange={(e) => {
                                                    setData(
                                                        "first_name",
                                                        e.target.value
                                                    );
                                                }}
                                                placeholder="First name"
                                            />
                                            {errors.formErrors && errorFormId == data.id ? (
                                                <label className="invalid-feedback d-block">
                                                    {
                                                        errors.formErrors
                                                            .first_name
                                                    }
                                                </label>
                                            ) : (
                                                ""
                                            )}
                                        </div>
                                    </div>
                                    <div className="col-lg-6">
                                        <div className="mb-3">
                                            <label
                                                htmlFor="last-name"
                                                className="form-label"
                                            >
                                                Last Name
                                            </label>
                                            <input
                                                type="text"
                                                className="form-control"
                                                name="last_name"
                                                id="last-name"
                                                value={data.last_name}
                                                onChange={(e) => {
                                                    setData(
                                                        "last_name",
                                                        e.target.value
                                                    );
                                                }}
                                                placeholder="Last name"
                                            />
                                            {errors.formErrors  && errorFormId == data.id ? (
                                                <label className="invalid-feedback d-block">
                                                    {
                                                        errors.formErrors
                                                            .last_name
                                                    }
                                                </label>
                                            ) : (
                                                ""
                                            )}
                                        </div>
                                    </div>
                                </div>
                                <div className="row">
                                    <div className="col-lg-6">
                                        <div className="mb-0">
                                            <label
                                                htmlFor="email"
                                                className="form-label"
                                            >
                                                Email{" "}
                                            </label>
                                            <input
                                                type="text"
                                                className="form-control"
                                                name="email"
                                                id="email"
                                                value={data.email}
                                                onChange={(e) => {
                                                    setData(
                                                        "email",
                                                        e.target.value
                                                    );
                                                }}
                                                placeholder="Email"
                                            />
                                            {errors.formErrors  && errorFormId == data.id ? (
                                                <label className="invalid-feedback d-block">
                                                    {errors.formErrors.email}
                                                </label>
                                            ) : (
                                                ""
                                            )}
                                        </div>
                                    </div>
                                    <div className="col-lg-6">
                                        <div className="mb-0">
                                            <label
                                                htmlFor="department"
                                                className="form-label"
                                            >
                                                Department{" "}
                                            </label>
                                            <input
                                                type="text"
                                                className="form-control"
                                                name="department"
                                                id="department"
                                                value={data.department}
                                                onChange={(e) => {
                                                    setData(
                                                        "department",
                                                        e.target.value
                                                    );
                                                }}
                                                placeholder="Department"
                                            />
                                            {errors.formErrors  && errorFormId == data.id ? (
                                                <label className="invalid-feedback d-block">
                                                    {errors.formErrors.department}
                                                </label>
                                            ) : (
                                                ""
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </Modal.Body>
                            <Modal.Footer className='px-3 pt-0 pb-3'>
                                <button
                                    type="button"
                                    className="btn btn-secondary waves-effect"
                                    onClick={() => setUserModelShow(false)}
                                >
                                    Close
                                </button>
                                {!processing ? (
                                    <button
                                        type="submit"
                                        className="btn btn-primary waves-effect waves-light"
                                    >
                                        Save Changes
                                    </button>
                                ) : (
                                    <Button variant="primary" disabled>
                                        <Spinner
                                            animation="border"
                                            size="sm"
                                        />{" "}
                                        Updating...
                                    </Button>
                                )}
                            </Modal.Footer>
                        </form>
                    </Modal>
                </ContentLoader>
            </Fragment>
        </AppLayout>
    );
}

export default UsersAndGroups;