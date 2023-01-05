import React, {
    forwardRef,
    Fragment,
    useImperativeHandle,
    useState,
} from "react";
import Modal from "react-bootstrap/Modal";
import DataTable from "../../../../../common/custom-datatable/AppDataTable";

function AddExistingUserModal(props, ref) {
    const [addExistingUserModalShow, setAddExistingUserModalShow] = useState(false);
    const refreshDataTable = false;
    const [error, setError] = useState("");
    const title = "Add System Users To Group";

    const [groupUsers, setGroupUsers] = useState(props.groupUsers);
    const fetchURL = route("policy-management.users-and-groups.users.import-user-data");

    useImperativeHandle(ref, () => ({
        addExistingUser() {
            localStorage.removeItem("controlPerPage");
            localStorage.removeItem("controlCurrentPage");
            setAddExistingUserModalShow(true);
        },
        checkSelectedUsers() {
            //For User Edit

            let dataRow = [];

            for (let user of props.groupUsers.groupsData) {
                dataRow[1] = user.user_first_name
                dataRow[2] = user.user_last_name
                dataRow[3] = user.user_email
                dataRow[4] = user.user_department
                handleCheckboxChange(dataRow);
            }
        },
        clearAllStates() {
            setCheckedState([]);
            setGroupUsers({ groupsData: [] });
        },
    }));

    const [checkedState, setCheckedState] = useState([]);

    function handleCheckboxChange(rowData) {

        let currentData = groupUsers.groupsData;
        let data = {
            user_first_name: rowData[1],
            user_last_name: rowData[2],
            user_email: rowData[3],
            user_department: rowData[4],
        };

        //Checkbox State Management
        let checked = checkedState.filter((item) => item.email == rowData[3]) //checking if checkbox is checked

        if (checked[0]) {
            if (checked[0].checked == "checked") {
                //checkbox is checked so need to uncheck
                checked[0].checked = null;
                const indexToRemove = currentData.indexOf(currentData.filter((item) => item.user_email == rowData[3])[0])
                if (indexToRemove > -1) {
                    currentData.splice(indexToRemove, 1)
                }
            }
            else {
                //check unchecck full cycle complete
                checked[0].checked = "checked";
                currentData.push(data); // Data State
            }
        } else {
            //this is where you first enter after you open the modal
            //checkbox is unchecked so need to check
            let currentState = {
                email: rowData[3],
                checked: 'checked'
            }
            checkedState.push(currentState) // Checkbox State
            currentData.push(data); // Data State
        }


        let finalData = {
            groupsData: currentData,
        };
        setGroupUsers(finalData);
    }

    function addToGroup() {
        setAddExistingUserModalShow(false);
        props.actionFunction(groupUsers);
    }

    function computeCheckedOrNot(row) {
        if (row[3]) {
            let checked = checkedState.filter((item) => item.email == row[3])
            if (checked[0]) {
                if (checked[0].checked == "checked") {
                    return "checked";
                }
            } else {
                return null;
            }
        }
    }

    function onModalClose() {
        setError('')
        setAddExistingUserModalShow(false)
    }

    const addUserColumns = [
        {
            accessor: "0",
            label: "Select",
            priority: 3,
            position: 1,
            minWidth: 140,
            sortable: false,
            CustomComponent: ({ row }) => {
                return (
                    <Fragment>
                        <div className="checkbox checkbox-success">
                            <input
                                id={"user-checkbox" + row[0]}
                                type="checkbox"
                                checked={computeCheckedOrNot(row)}
                                onChange={() => handleCheckboxChange(row)}
                                className='user-checkbox-input'
                            />
                            <label
                                className="user-checkbox-label"
                                htmlFor={"user-checkbox" + row[0]}
                            ></label>
                        </div>
                    </Fragment>
                );
            },
        },
        {
            accessor: "1",
            label: "First Name",
            priority: 1,
            position: 2,
            minWidth: 140,
        },
        {
            accessor: "2",
            label: "Last Name",
            priority: 2,
            position: 3,
            minWidth: 140,
        },
        {
            accessor: "3",
            label: "Email",
            priority: 3,
            position: 4,
            minWidth: 160,
        },
        {
            accessor: "4",
            label: "Department",
            priority: 2,
            position: 5,
            minWidth: 150,
        }
    ];

    return (
        <div>
            {error ? (
                <span className="invalid-feedback d-block">{error}</span>
            ) : (
                ""
            )}
            <Modal
                show={addExistingUserModalShow}
                onHide={onModalClose}
                dialogClassName="modal-90w"
                aria-labelledby="example-custom-modal-styling-title"
                size="xl"
            >
                <Modal.Header className="px-3 pt-3 pb-0" closeButton>
                    <Modal.Title
                        id="example-custom-modal-styling-title"
                        className="my-0"
                    >
                        {title}
                    </Modal.Title>
                </Modal.Header>
                <Modal.Body className="p-3">
                    <div className="modal-body table-container">
                        <DataTable
                            columns={addUserColumns}
                            fetchUrl={fetchURL}
                            refresh={refreshDataTable}
                            tag="project-controls-add-existing-user-modal"
                            search
                            emptyString="No data found"
                        />
                    </div>
                    <div className="modal-footer">
                        <button
                            type="button"
                            className="btn btn-secondary waves-effect"
                            data-dismiss="modal"
                            onClick={onModalClose}
                        >
                            Close
                        </button>
                        <button
                            type="button"
                            className="btn btn-primary waves-effect waves-light"
                            id="submit-group-btn"
                            onClick={() => addToGroup()}
                        >
                            Add To Group
                        </button>
                    </div>
                </Modal.Body>
            </Modal>
        </div>
    );
}

export default forwardRef(AddExistingUserModal);
