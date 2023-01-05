import { Inertia } from "@inertiajs/inertia";
import { Link } from "@inertiajs/inertia-react";
import { Dropdown } from "react-bootstrap";

import '../../integrations/responsive.css';

function Standard(props) {
    const { standard } = props;
   
    const handleDelete = async (standardId) => {
        AlertBox(
            {
                title: "Are you sure?",
                text: "You want to delete this standard?",
                confirmButtonColor: "#ff0000",
                allowOutsideClick: false,
                icon: "warning",
                iconColor: '#ff0000',
                showCancelButton: true,
                confirmButtonText: "Yes, delete it!",
            },
            function (result) {
                if (result.isConfirmed) {
                    Inertia.delete(route("compliance-template-delete",standardId),{
                        preserveState: false,
                    });
                }
            }
        );
    };

    return (
        <div className="card bg-pattern h-100">
            <div className="card-body">
                {standard.is_default == 1 &&
                    <div>
                        <span
                            className="badge bg-dark"
                            style={{ textTransform: "capitalize", marginBottom: "2px" }}
                        >
                            {standard.automation}
                            {/* Automation Coming Soon */}
                        </span>
                    </div>
                }
                <div><span className="badge bg-soft-info text-info" style={{ marginBottom: "2px" }}>{standard.controls_count} Controls</span></div>
                <div><span className="badge bg-soft-info text-info">{standard.version}</span></div>
                <Dropdown className='d-inline-block float-end' style={{ marginTop: '-45px' }}>
                    <Dropdown.Toggle
                        as="a"
                        bsPrefix="card-drop arrow-none cursor-pointer"
                    >
                        <i className="mdi mdi-dots-horizontal m-0 text-muted h3" />
                    </Dropdown.Toggle>

                    <Dropdown.Menu className="dropdown-menu-end">
                        {/* <Link
                            href={route("compliance-template-view-controls", standard.id)}
                            className="dropdown-item d-flex align-items-center"
                        >
                            <i className="mdi mdi-eye-outline font-18 me-1"></i> View
                        </Link> */}
                        <Link
                            href={route("compliance-template-dublicate", standard.id)}
                            className="dropdown-item d-flex align-items-center"
                        >
                            <i className="mdi mdi-content-copy font-18 me-1"></i> Duplicate Standard
                        </Link>
                        {!standard.is_default && (
                            <Link
                                href={route("compliance-template-create-controls", standard.id)}
                                className="dropdown-item d-flex align-items-center"
                            >
                                <i className="mdi mdi-plus-box-outline font-18 me-1"></i> Add Control
                            </Link>
                        )}
                        {!standard.is_default && (
                            <Link
                                href={route("compliance-template-edit", standard.id)}
                                className="dropdown-item d-flex align-items-center"
                            >
                                <i className="mdi mdi-pencil-outline font-18 me-1"></i> Edit Information
                            </Link>
                        )}
                        {!standard.is_default && (
                            <button
                                onClick={() => handleDelete(standard.id)}
                                className="dropdown-item d-flex align-items-center"
                            >
                                <i className="mdi mdi-delete-outline font-18 me-1"></i> Delete
                            </button>
                        )}
                    </Dropdown.Menu>
                </Dropdown>
                <div className="clearfix" />
                <div className="text-center">
                    <img src={standard.logo_link} alt="" className="avatar-xl mb-3" />
                    <h4 className="mb-1 font-20 clamp clamp-1">{decodeHTMLEntity(standard.name)}</h4>
                </div>

                <p className="description font-14 text-center text-muted">{standard.description}</p>

                <div className="text-center">
                    <Link
                        className="btn btn-sm btn-primary"
                        href={route("compliance-template-view-controls", standard.id)}
                    >
                        View
                    </Link>
                </div>
            </div>
        </div>
    )
}

export default Standard;