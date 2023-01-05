import React, { Fragment, useEffect, useState } from "react";

import { Dropdown, Nav, Tab } from "react-bootstrap";
import { tableActions } from "../../store/dataTable/customDataTable";
import { useDispatch } from 'react-redux';
import { Link } from "@inertiajs/inertia-react";

import FlashMessages from '../../common/FlashMessages'
import AppLayout from "../../layouts/app-layout/AppLayout";
import Standard from "./components/Standard";

import './style.scss';

export default function StandardList(props) {
    const [categories, setCategories] = useState([]);
    const [standards, setStandards] = useState([]);
    const [currentPage, setCurrentPage] = useState(1);
    const [totalPageCount, setTotalPageCount] = useState(1);
    const [activeCategoryIndex, setActiveCategoryIndex] = useState(0);
    const [searchKeyword, setSearchKeyword] = useState('');
    const dispatch = useDispatch();
    useEffect(()=>{
        dispatch(tableActions.customDataTableSetInitialState({
            tableTag:'compliance-template'
        }))
    },[]);
    useEffect(() => {
        setCategories(props.categories);

        if (!searchKeyword.length)
            setStandards(setPagination(props.categories[activeCategoryIndex].standards));
        else {
            handleSearchQueryChange(searchKeyword, false);
        }
    }, [activeCategoryIndex, currentPage]);

    const setPagination = (paginationData) => {
        if (paginationData.length > 6)
            setTotalPageCount(Math.ceil(((paginationData.length - 6) / 3) + 1));
        else
            setTotalPageCount(1);

        if (currentPage === 1) {
            let perPage = 6;
            return paginationData.slice((currentPage - 1) * perPage, currentPage * perPage);
        } else {
            let perPage = 3;
            return paginationData.slice(0, (currentPage * perPage) + perPage);
        }
    }

    const handleSearchQueryChange = (keyword, resetPage = true) => {
        const searchTerm = keyword.toLowerCase().trim();

        setSearchKeyword(searchTerm);
        if (resetPage)
            setCurrentPage(1);

        let filteredData = props.categories[activeCategoryIndex].standards.filter(item => {
            return item.name.toLowerCase().match(new RegExp(searchTerm, 'g'))
        });

        setStandards(setPagination(filteredData));
    }

    //reset all state on category change
    const resetState = (index) => {
        if (activeCategoryIndex !== index) {
            setCurrentPage(1);
            setTotalPageCount(1);
            setActiveCategoryIndex(index);
            setSearchKeyword('');
        }
        return true;
    }

    const loadMoreData = () => {
        setCurrentPage(currentPage + 1);

        setTimeout(() => {
            let scrollingElement = (document.scrollingElement || document.body);

            scrollingElement.scrollTop = scrollingElement.scrollHeight;
        }, 50);

        return true;
    }

    useEffect(() => {
        document.title = "Compliance Templates";
    }, []);

    const fetchURL = route('compliance-template-get-json-data');
    let propsData = { props };
    const [statusMessage, setStatusMessage] = useState(
        propsData.props.flash.success ? propsData.props.flash.success : null
    );

    //For DataTable
    const columns = [
        {
            accessor: "id",
            label: "ID",
            priorityLevel: 1,
            position: 1,
            minWidth: 50,
            sortable: false,
        },
        {
            accessor: "name",
            label: "Name",
            priorityLevel: 1,
            position: 2,
            minWidth: 150,
            sortable: false,
        },
        {
            accessor: "version",
            label: "Version",
            priorityLevel: 1,
            position: 3,
            minWidth: 150,
            sortable: false,
        },
        {
            accessor: "controls",
            label: "Controls",
            priorityLevel: 1,
            position: 4,
            minWidth: 150,
            sortable: false,
            CustomComponent: ({ row }) => {
                return (
                    <Fragment>
                        <span className="badge bg-info">
                            {row.controls_count} Controls
                        </span>
                    </Fragment>
                );
            },
        },
        {
            accessor: "automation",
            label: "Automation",
            priorityLevel: 1,
            position: 4,
            minWidth: 150,
            sortable: false,
            CustomComponent: ({ row }) => {
                return (
                    <Fragment>
                        <div
                            className={
                                "badge " +
                                (row.automation == "Coming Soon"
                                    ? "bg-warning"
                                    : "bg-purple")
                            }
                            style={{ textTransform: "capitalize" }}
                        >
                            {row.automation}
                        </div>
                    </Fragment>
                );
            },
        },
        {
            accessor: "created_date",
            label: "Created On",
            priorityLevel: 2,
            position: 5,
            minWidth: 150,
            sortable: false,
        },
        {
            accessor: "action",
            label: "Action",
            priorityLevel: 0,
            position: 6,
            minWidth: 150,
            sortable: false,
            CustomComponent: ({ row }) => {
                return (
                    <Fragment>
                        <Dropdown className='d-inline-block'>
                            <Dropdown.Toggle
                                as="a"
                                bsPrefix="card-drop arrow-none cursor-pointer"
                            >
                                <i className="mdi mdi-dots-horizontal m-0 text-muted h3" />
                            </Dropdown.Toggle>

                            <Dropdown.Menu className="dropdown-menu-end">
                                <Link
                                    href={route("compliance-template-view-controls", row.id)}
                                    className="dropdown-item d-flex align-items-center"
                                >
                                    <i className="mdi mdi-eye-outline font-18 me-1"></i> View
                                </Link>
                                <Link
                                    href={route("compliance-template-dublicate", row.id)}
                                    className="dropdown-item d-flex align-items-center"
                                >
                                    <i className="mdi mdi-content-copy font-18 me-1"></i> Duplicate Standard
                                </Link>
                                {!row.is_default && (
                                    <Link
                                        href={route("compliance-template-create-controls", row.id)}
                                        className="dropdown-item d-flex align-items-center"
                                    >
                                        <i className="mdi mdi-plus-box-outline font-18 me-1"></i> Add Control
                                    </Link>
                                )}
                                {!row.is_default && (
                                    <Link
                                        href={route("compliance-template-edit", row.id)}
                                        className="dropdown-item d-flex align-items-center"
                                    >
                                        <i className="mdi mdi-pencil-outline font-18 me-1"></i> Edit Information
                                    </Link>
                                )}
                                {!row.is_default && (
                                    <button
                                        onClick={() => handleDelete(row.id)}
                                        className="dropdown-item d-flex align-items-center"
                                    >
                                        <i className="mdi mdi-delete-outline font-18 me-1"></i> Delete
                                    </button>
                                )}
                            </Dropdown.Menu>
                        </Dropdown>
                    </Fragment>
                );
            },
        },
    ];

    const breadcumbsData = {
        title: "View Standards",
        breadcumbs: [
            {
                title: "Administration",
                href: "",
            },
            {
                title: "Compliance Template",
                href: route("compliance-template-view"),
            },
            {
                title: "View",
                href: "",
            },
        ],
    };

    return (
        // <ComplianceTemplate breadcumbsData={breadcumbsData}>
        //     {statusMessage && (
        //         <Alert
        //             variant="success"
        //             onClose={() => setStatusMessage(null)}
        //             dismissible
        //         >
        //             {statusMessage}
        //         </Alert>
        //     )}
        //     <div className="row">
        //         <div className="col-12">
        //             <div className="card">
        //                 <div className="card-body">
        //                     <Link
        //                         href={route("compliance-template-create")}
        //                         type="button"
        //                         className="btn btn-sm btn-primary waves-effect waves-light float-end"
        //                     >
        //                         <i
        //                             className="mdi mdi-plus-circle"
        //                             title="Add New Standard"
        //                         ></i>{" "}
        //                         Add New Standard
        //                     </Link>
        //                     <h4 className="header-title mb-4">
        //                         Manage Standards
        //                     </h4>

        //                     <DataTable
        //                         columns={columns}
        //                         fetchURL={fetchURL}
        //                         search
        //                     />
        //                 </div>
        //             </div>
        //         </div>
        //         {/* <!-- end col --> */}
        //     </div>
        //     {/* <!-- end row --> */}
        // </ComplianceTemplate>

        <AppLayout>
            <div id="integration-page">
                <div className="row mt-4 mb-3">
                    <div className="col-md-8">
                        <h4>Manage Standards</h4>
                    </div>
                    <div className="col-md-4 clearfix">
                        <div className="float-sm-end">
                            <div className="row align-items-center">
                                <div className="col-12">
                                    <Link
                                        href={route("compliance-template-create")}
                                        type="button"
                                        id="add-standard-button"
                                        className="btn btn-sm btn-primary waves-effect waves-light float-sm-end"
                                    >
                                        <i
                                            className="mdi mdi-plus-circle"
                                            title="Add New Standard"
                                        ></i>{" "}
                                        Add New Standard
                                    </Link>
                                </div>
                                <div className="col-12" style={{ paddingTop: '20px' }}>
                                    <input
                                        type="text"
                                        onChange={e => handleSearchQueryChange(e.target.value)}
                                        value={searchKeyword}
                                        placeholder="Search..."
                                        id="standard-search"
                                        className="form-control form-control-sm float-sm-end"
                                        style={{ width: 'auto' }}
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <Tab.Container id="company-list" defaultActiveKey="0">
                    <div className="row">
                        <div className="col-sm-3">
                            <Nav variant="pills" className="flex-column">
                                {
                                    categories.map((category, index) => {
                                        return (
                                            <Nav.Item key={index.toString()}>
                                                <Nav.Link eventKey={category.id}
                                                    onClick={() => resetState(index)}>{category.name}</Nav.Link>
                                            </Nav.Item>
                                        );
                                    })
                                }
                            </Nav>
                        </div>
                        <div className="col-sm-9">
                            <FlashMessages></FlashMessages>
                            <Tab.Content>
                                {
                                    categories.map((category, index) => {
                                        return (
                                            <Tab.Pane eventKey={category.id} key={index.toString()}>
                                                <div className="row row-cols-1 row-cols-md-2 row-cols-lg-3 gy-3" style={{ marginTop: '-43px' }}>
                                                    {standards.map((standard, index) => {
                                                        return (
                                                            <div className="col" key={index.toString()}>
                                                                <Standard standard={standard} />
                                                            </div>
                                                        );
                                                    })}
                                                </div>
                                                {currentPage < totalPageCount && <div className="row mt-4">
                                                    <div className="col-12">
                                                        <div className="text-center">
                                                            <a onClick={() => loadMoreData()}
                                                                className="btn btn-sm btn-primary">
                                                                {/*<i className="mdi mdi-spin mdi-loading me-2"/>*/}
                                                                Load more
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>}
                                            </Tab.Pane>
                                        );
                                    })
                                }
                            </Tab.Content>
                        </div>
                    </div>
                </Tab.Container>
            </div>
        </AppLayout>


    );
}
