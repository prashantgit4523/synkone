import React, { Fragment, useState, useEffect } from 'react';
import { useDispatch } from "react-redux";
import { Inertia } from '@inertiajs/inertia';
import DataTable from '../../../common/custom-datatable/AppDataTable';
import { Link, usePage } from "@inertiajs/inertia-react";
import "../styles/react-collapsing-table.css";
import Dropdown from "react-bootstrap/Dropdown";
import FlashMessages from "../../../common/FlashMessages";
import UserLayout from "../UserLayout";
import Select from "../../../common/custom-react-select/CustomReactSelect";
import Swal from "sweetalert2";

const deleteUser = (id, successCallback, transfer_to = null) => {
    return AlertBox(
        {
            title: "Are you sure?",
            text: "You will not be able to recover this user!",
            showCancelButton: true,
            confirmButtonColor: "#ff0000",
            confirmButtonText: "Yes, delete it!",
            icon: 'warning',
            iconColor: '#ff0000',
        },
        function (result) {
            if (result.isConfirmed) {
                axiosFetch.delete(
                    route("admin-user-management-delete", id),
                    {
                        params: {
                            target_id: transfer_to
                        }
                    }
                ).then(res => {
                    if (res.status === 200 && res.data.success) {
                        AlertBox({
                            text: res.data.message,
                            confirmButtonColor: '#b2dd4c',
                            icon: 'success',
                        });
                        successCallback();
                    } else {
                        AlertBox({
                            text: res.data.message,
                            confirmButtonColor: '#f1556c',
                            icon: 'error',
                        });
                    }
                });
            }
        }
    );
}

export const handleUserDelete = (id, successCallback) => {
    axiosFetch
        .get(route('admin-user-management-check-admin-ownership', id))
        .then(({data}) => {
            if(data.has_ownership){
                // transfer ownership
                return axiosFetch
                    .get(route("user.assignments-transferable-users-with-department", id))
                    .then((response) => {
                        let selectUserOptions = [];

                        if (response.data.success) {
                            let users = response.data.data;
                            selectUserOptions = users.map(u => ({value: u.id, label: u.full_name + ' - ' + u.email}));
                        }

                        let selectedUser = null;
                        AlertBox({
                            confirmButtonColor: '#b2dd4c',
                            imageUrl: `${appBaseURL}/assets/images/info1.png`,
                            imageWidth: 120,
                            showCloseButton: true,
                            showCancelButton: true,
                            html: (
                                <div>
                                    <span style={{fontSize: '1.125em'}}>Select a user to transfer ownership to:</span>
                                    <div style={{padding: "4px", marginTop: '10px', fontSize: '16px'}}>
                                        <Select
                                            placeholder="Select User..."
                                            onChange={value => {
                                                selectedUser = value;
                                            }}
                                            menuPortalTarget={document.body}
                                            styles={{
                                                menuPortal: (base) => ({
                                                    ...base,
                                                    zIndex: 9999,
                                                }),
                                            }}
                                            options={selectUserOptions}
                                        />
                                    </div>
                                </div>
                            ),
                            preConfirm: () => deleteUser(id, successCallback, selectedUser?.value)
                        });
                    });
            }

            deleteUser(id, successCallback);
        });
}

function UserList(props) {

    useEffect(() => {
        document.title = "User Management";
    }, []);

    const dispatch = useDispatch();

    const fetchURL = "users/get-user-data-react";
    const [refresh, setRefresh] = useState(false);
    const { authUser } = usePage().props;

    const handleUserEdit = async (id) => {
        Inertia.get(route("admin-user-management-edit", id));
    };

    function disableUser(id) {
        axiosFetch.post(route('admin-user-management-disable-user', id))
            .then(res => {
                if (res.status === 200 && res.data.success) {
                    AlertBox({
                        text: res.data.message,
                        confirmButtonColor: "#b2dd4c",
                        icon: 'success',
                    });
                    setRefresh((prevState) => !prevState);
                } else {
                    AlertBox({
                        text: res.data.message,
                        confirmButtonColor: "#f1556c",
                        icon: 'error',
                    });
                }
            })
            .catch(function (e) {
                console.log(e);
            });
    }

    const handleUserDisable = async (id) => {
        axiosFetch.get(route("user.project-assignments", id)).then((res) => {
            if (res.data.should_be_transferred) {
                axiosFetch
                    .get(route("user.assignments-transferable-users-with-department", id))
                    .then((res) => {
                        let selectUserOptions = [];

                        if (res.data.success) {
                            let users = res.data.data;
                            selectUserOptions = users.map(
                                (user) => ({ value: user.id, label: user.full_name + ' - ' + user.email })
                            );
                        }

                        let selectedUser = null;
                        AlertBox({
                            confirmButtonColor: '#b2dd4c',
                            imageUrl: `${appBaseURL}/assets/images/info1.png`,
                            imageWidth: 120,
                            showCloseButton: true,
                            showCancelButton: true,
                            html: (
                                <div>
                                    <span style={{ fontSize: '1.125em' }}>Select a user to transfer ownership to:</span>
                                    <div style={{ padding: "4px", marginTop: '10px', fontSize: '16px' }}>
                                        <Select
                                            placeholder="Select User..."
                                            onChange={value => { selectedUser = value; }}
                                            menuPortalTarget={document.body}
                                            styles={{
                                                menuPortal: (base) => ({
                                                    ...base,
                                                    zIndex: 9999,
                                                }),
                                            }}
                                            options={selectUserOptions}
                                        />
                                    </div>
                                </div>
                            ),
                            preConfirm: () => {
                                dispatch({ type: "reportGenerateLoader/show", payload: "Transfering Assignments..." });
                                return axiosFetch
                                    .post(route("user.transfer-assignments", id), { transfer_to: selectedUser?.value })
                                    .then((res) => {
                                        if (res.data.success) {
                                            disableUser(id);
                                        } else {
                                            AlertBox({
                                                title: "Oops...",
                                                text: res.data.message ?? res.data.exception,
                                                icon: 'error',
                                                confirmButtonColor: "#ff0000",
                                            });
                                        }
                                    })
                                    .catch(({ response: { data: { errors } } }) => {
                                        Object.keys(errors).forEach(k => {
                                            Swal.showValidationMessage(errors[k][0])
                                        }
                                        );
                                    })
                                    .finally(() => dispatch({ type: "reportGenerateLoader/hide" }));
                            }
                        },
                            function (confirmed) {
                            }
                        );
                    });
            } else {
                disableUser(id);
            }
        });
    };

    const handleUserActive = async (id) => {
        axiosFetch
            .get(route("admin-user-management-activate-user", id))
            .then((res) => {
                if (res.status === 200 && res.data.success) {
                    AlertBox({
                        text: res.data.message,
                        confirmButtonColor: "#b2dd4c",
                        icon: 'success',
                    });
                    setRefresh((prevState) => !prevState);
                } else {
                    AlertBox({
                        text: res.data.message,
                        confirmButtonColor: "#f1556c",
                        icon: 'error',
                    });
                }
            })
            .catch(function (e) {
                console.log(e);
            });
    };

    //For DataTable
    const columns = [
        
        {
            accessor: "auth_method",
            label: "Auth Method",
            priority: 1,
            position: 1,
            minWidth: 120,
            sortable: true,
        },
        {
            accessor: "first_name",
            label: "First Name",
            priority: 1,
            position: 2,
            minWidth: 100,
            sortable: true,
        },
        {
            accessor: "last_name",
            label: "Last Name",
            priority: 1,
            position: 3,
            minWidth: 100,
            sortable: true,
        },
        {
            accessor: "edited_department_name",
            label: "Department",
            priority: 2,
            position: 4,
            minWidth: 140,
            sortable: true,
            as: 'department_name'
        },
        {
            accessor: "email",
            label: "Email",
            priority: 3,
            position: 5,
            minWidth: 160,
            sortable: true,
        },
        {
            accessor: "contact_number",
            label: "Phone",
            priority: 2,
            position: 6,
            minWidth: 140,
            CustomComponent: ({ row }) => {
                return (
                    <Fragment>
                        (&nbsp;{row.contact_number_country_code} &nbsp;)&nbsp;
                        {row.contact_number}
                    </Fragment>
                );
            },
        },
        {
            accessor: "role_names",
            label: "Roles",
            priority: 1,
            position: 7,
            minWidth: 120,
            CustomComponent: ({ row }) => {
                return (
                    <Fragment>
                        {row.roles.map((role, index) => {
                            return (
                                <span key={index} className="badge bg-soft-info text-info">
                                    {role.name}
                                </span>
                            );
                        })}
                    </Fragment>
                );
            },
        },
        {
            accessor: "status",
            label: "Status",
            priority: 1,
            position: 8,
            minWidth: 100,
            sortable: true,
            CustomComponent: ({ row }) => {
                return (
                    <Fragment>
                        <div
                            className={
                                "badge " +
                                (row.status == "active"
                                    ? "bg-info"
                                    : row.status == "disabled"
                                        ? "bg-danger"
                                        : "bg-warning")
                            }
                            style={{ textTransform: "capitalize" }}
                        >
                            {row.status}
                        </div>
                    </Fragment>
                );
            },
        },
        {
            accessor: "created_date",
            label: "Created At",
            priority: 1,
            position: 9,
            minWidth: 150,
            sortable: true,
            as: 'created_at'
        },
        {
            accessor: "updated_date",
            label: "Updated At",
            priority: 1,
            position: 10,
            minWidth: 150,
            sortable: true,
            as: 'updated_at'
        },
        {
            accessor: "last_login",
            label: "Last Login",
            priority: 2,
            position: 11,
            minWidth: 150,
            sortable: true,
        },
        {
            accessor: "action",
            label: "Action",
            priority: 4,
            position: 12,
            minWidth: 90,
            sortable: false,
            canOverflow: true,
            CustomComponent: ({ row }) => {
                return (
                    <Fragment>
                        <Dropdown className="d-inline-block">
                            <Dropdown.Toggle
                                variant=""
                                size="sm"
                                className="table-action-btn arrow-none btn"
                                aria-expanded="false"
                            >
                                <i className="mdi mdi-dots-horizontal text-muted h3 my-0"></i>
                            </Dropdown.Toggle>

                            <Dropdown.Menu className="dropdown-menu-end">
                                <Dropdown.Item
                                    href="#"
                                    onClick={() => handleUserEdit(row.id)}
                                >
                                    <i className="mdi mdi-pencil-outline me-1 text-muted font-18 vertical-middle"></i>
                                    Edit
                                </Dropdown.Item>
                                {row.status === "active" &&
                                    row.status !== "unverified" &&
                                    row.id != authUser.id && (
                                        <Dropdown.Item
                                            href="#"
                                            onClick={() =>
                                                handleUserDisable(row.id)
                                            }
                                        >
                                            <i className="mdi mdi-delete-outline me-1 text-muted font-18 vertical-middle"></i>
                                            Disable
                                        </Dropdown.Item>
                                    )}
                                {row.status === "disabled" &&
                                    row.status !== "unverified" && (
                                        <Dropdown.Item
                                            href="#"
                                            onClick={() =>
                                                handleUserActive(row.id)
                                            }
                                        >
                                            <i className="mdi mdi-account-check-outline me-2 text-muted font-18 vertical-middle"></i>
                                            Activate
                                        </Dropdown.Item>
                                    )}
                                {(row.status === 'unverified' || row.status === 'disabled') &&
                                    <Dropdown.Item href="#" onClick={() => handleUserDelete(row.id, () => setRefresh(prevState => (!prevState)))}>
                                        <i className='mdi mdi-delete-outline me-1 text-muted font-18 vertical-middle'></i>Delete
                                    </Dropdown.Item>
                                }
                            </Dropdown.Menu>
                        </Dropdown>
                    </Fragment>
                );
            },
        },
    ];

    const breadcumbsData = {
        title: "Users View",
        breadcumbs: [
            {
                "title": "User Management",
                "href": ""
            },
            {
                "title": "Users",
                "href": route('admin-user-management-view')
            },
            {
                title: "List",
                href: "",
            },
        ],
    };

    return (
        <UserLayout breadcumbsData={breadcumbsData}>
            <FlashMessages />
            <div className="col-xl-12">
                <div className="card">
                    <div className="card-body" id="users-list">
                        <Link
                            href={route("admin-user-management-create")}
                            type="button"
                            className="btn btn-sm btn-primary waves-effect waves-light float-end"
                        >
                            <i className="mdi mdi-plus-circle"></i> Add User
                        </Link>
                        <h4 className="header-title mb-4">Manage Users</h4>

                        <DataTable
                            columns={columns}
                            fetchUrl={fetchURL}
                            refresh={refresh}
                            tag="users-list"
                            search
                            emptyString='No data found'
                        />
                    </div>
                </div>
            </div>
        </UserLayout>
    );
}

export default UserList;
