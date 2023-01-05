import React, { useState } from "react";
import { Button, Modal } from "react-bootstrap";
import { useForm } from "@inertiajs/inertia-react";
import { Inertia } from "@inertiajs/inertia";
import Select from "../../../../common/custom-react-select/CustomReactSelect";
import { useForm as useReactHookForm } from "react-hook-form";
import { useDispatch } from "react-redux";
import { fetchDataScopeDropdownTreeData } from "../../../../store/actions/data-scope-dropdown";

const AddDepartmentModal = ({ config, handleClose }) => {
    const dispatch = useDispatch();
    const [departments, setDepartments] = useState([]);
    const { data, setData, processing, errors, post, reset, clearErrors } = useForm({
        name: "",
        parent_id: null,
    });

    const {
        register,
        formState: { errors: reactErrors },
        handleSubmit,
        clearErrors: clearReactErrors,
    } = useReactHookForm({
        mode: "onSubmit",
    });

    const fetchDepartments = async () => {
        try {
            const response = await axiosFetch.get(
                route(
                    "global-settings.organizations.departments",
                    config.organization_id
                )
            );

            const departmentsSelectOptions = response.data.data.map((d) => ({
                value: d.id,
                label: d.name,
            }));
            departmentsSelectOptions.push({
                value: 0,
                label: "No Parent",
            });
            setDepartments(departmentsSelectOptions);
        } catch (e) { }
    };

    const submit = () => {
        post(
            route(
                "global-settings.organizations.departments.store",
                config.organization_id
            ),
            {
                onSuccess: () => {
                    reset("name");
                    Inertia.reload({ only: ["organizations"] });
                    handleClose();

                    /* Updating the data scope dropdown data */
                    dispatch(fetchDataScopeDropdownTreeData());
                },
            }
        );
    };

    React.useEffect(() => {
        if (config.shown) {
            clearErrors();
            if (config.organization_id) {
                fetchDepartments();
            }
            setData("parent_id", config.department_id);
        }
    }, [config]);

    return (
        <Modal show={config.shown} onHide={handleClose}>
            <form
                onSubmit={handleSubmit(submit)}
                className="absolute-error-form"
                id="recoverpw-form"
            >
                <Modal.Header className="px-3 pt-3 pb-0" closeButton>
                    <Modal.Title className="my-0">Add Department</Modal.Title>
                </Modal.Header>
                <Modal.Body className="p-3">
                    <div className="row">
                        <div className="col-md-12">
                            <div className="mb-3">
                                <label
                                    htmlFor="department-name"
                                    className="form-label"
                                >
                                    Name
                                </label>
                                <input
                                    {...register("name", {
                                        required: true,
                                        maxLength: 190,
                                    })}
                                    type="text"
                                    name="name"
                                    className="form-control"
                                    id="department-name"
                                    placeholder="Department Name"
                                    value={data.name}
                                    onChange={(e) => {
                                        setData("name", e.target.value);
                                        e.target.value && clearReactErrors('name');
                                    }
                                    }
                                />
                                {errors.name && (
                                    <div className="invalid-feedback d-block">
                                        {errors.name}
                                    </div>
                                )}
                                {reactErrors.name && reactErrors.name.type === "required" && (
                                    <div className="invalid-feedback d-block">
                                        The Department Name field is required.
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                    <div className="row">
                        <div className="col-md-12">
                            <div className="mb-0">
                                <label htmlFor="parent_id" className="form-label">
                                    Parent department
                                </label>
                                <Select
                                    className="react-select"
                                    classNamePrefix="react-select"
                                    value={departments.filter(
                                        (d) => d.value === data.parent_id
                                    )}
                                    onChange={(option) =>
                                        setData("parent_id", option.value)
                                    }
                                    options={departments}
                                />
                            </div>
                        </div>
                    </div>
                </Modal.Body>
                <Modal.Footer className="px-3 pt-0 pb-3">
                    <Button variant="secondary" onClick={handleClose}>
                        Close
                    </Button>
                    <Button
                        variant="info"
                        type="submit"
                        disabled={processing}
                    >
                        {processing ? "Saving" : "Save Changes"}
                    </Button>
                </Modal.Footer>
            </form>
        </Modal>
    );
};

export default AddDepartmentModal;
