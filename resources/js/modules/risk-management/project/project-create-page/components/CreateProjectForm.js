import React, {
    Fragment,
    useEffect,
    forwardRef,
    useRef,
    useImperativeHandle,
} from "react";
import { useForm } from "react-hook-form";
import { usePage } from "@inertiajs/inertia-react";
import { Inertia } from "@inertiajs/inertia";
import { useStateIfMounted } from "use-state-if-mounted";
import { useSelector,useDispatch } from "react-redux";
import axios from 'axios';

function CreateProjectForm(props, ref) {
    const { project, setFormSubmitting, updateWizardCurrentStep } = props;
    const { assignedControls } = usePage().props;
    const dispatch = useDispatch();

    const {
        register,
        reset,
        formState: { errors },
        trigger,
        setValue,
        getValues,
    } = useForm({
        mode: "onChange",
        // reValidateMode: 'onChange'
    });
    const appDataScope = useSelector(
        (state) => state.appDataScope.selectedDataScope.value
    );
    const dataScopeRef = useRef(appDataScope);

    /* Setting the form field value on load*/
    useEffect(() => {
        reset({
            name: project.name,
            description: project.description,
            standard_id: project.standard_id,
        });
    }, [project]);

    /**redirecting to index on scope change */
    useEffect(() => {
        if (dataScopeRef.current !== appDataScope) {
            Inertia.get(route("risks.projects.index"));
        }
    }, [appDataScope]);

    let cancelToken;

    /* Custome form validation rule*/
    const nameIsUnique = async (name) => {
        if (typeof cancelToken != typeof undefined) {
            cancelToken.cancel("Operation canceled.")
        }

        cancelToken = axios.CancelToken.source()

        try {
            const results = await axiosFetch.get(
                route("risks.projects.check-project-name-taken", project.id),
                { cancelToken: cancelToken.token, params: {name} }
            );

            return results.data;
        } catch (error) {
            console.log(error);
        }
    };

    /* checking from validity*/
    const isFormValid = async () => {
        return await trigger(["name", "description", "standard_id"]);
    };

    // The component instance will be extended
    // with whatever you return from the callback passed
    // as the second argument
    useImperativeHandle(ref, () => ({
        isFormValid,
        handleFormSubmit,
    }));

    const onSubmit = async (data) => {
        let submitURL = project.id
            ? route("risks.projects.projects-update", project.id)
            : route("risks.projects.projects-store");

        /* Setting form submitting loader */
        setFormSubmitting(true);

        data["data_scope"] = appDataScope;

        Inertia.post(submitURL, data, {
            onError: (page) => {
                updateWizardCurrentStep(1);
            },
            onFinish: () => {
                dispatch({type: "reportGenerateLoader/hide"});
            }
        });
    };

    const handleFormSubmit = async () => {
        dispatch({type: "reportGenerateLoader/show", payload: "Your project is being created, it's almost ready..."});
        const formData = getValues();

        /* Submitting form data */
        onSubmit(formData);
    };

    /* Triggers on standard update */
    const handleStandardChange = (e) => {
        reset({
            ...getValues(),
            standard_id: e.target.value,
        });
    };

    return (
        <Fragment>
            <form className="needs-validation" noValidate>
                <div className="row mb-3">
                    <label
                        className="col-md-3 form-label col-form-label"
                        htmlFor="name"
                    >
                        Project Name{" "}
                        <span className="required text-danger">*</span>
                    </label>
                    <div className="col-md-9">
                        <input
                            type="text"
                            {...register("name", {
                                required: true,
                                maxLength: 190,
                                validate: {
                                    asyncValidate: nameIsUnique,
                                },
                            })}
                            className="form-control"
                            id="project-name"
                            name="name"
                            placeholder="Project Name"
                            tabIndex={1}
                        />
                        {errors.name && errors.name.type === "required" && (
                            <div className="invalid-feedback d-block">
                                The Project Name field is required.
                            </div>
                        )}
                        {errors.name &&
                            errors.name.type === "asyncValidate" && (
                                <div className="invalid-feedback d-block">
                                    The Project Name already taken.
                                </div>
                            )}
                        {errors.name && errors.name.type === "maxLength" && (
                            <div className="invalid-feedback d-block">
                                The Project Name may not be greater than 190
                                characters.
                            </div>
                        )}
                    </div>
                </div>
                <div className="row mb-3">
                    <label
                        className="col-md-3 form-label col-form-label"
                        htmlFor="description"
                    >
                        {" "}
                        Description{" "}
                        <span className="required text-danger">*</span>
                    </label>
                    <div className="col-md-9">
                        <textarea
                            {...register("description", { required: true })}
                            name="description"
                            id="description"
                            className="form-control"
                            cols={30}
                            rows={5}
                            placeholder="Description"
                            tabIndex={2}
                        />
                        {errors.description &&
                            errors.description.type === "required" && (
                                <div className="invalid-feedback d-block">
                                    The Description field is required.
                                </div>
                            )}
                    </div>
                </div>
            </form>
        </Fragment>
    );
}

export default forwardRef(CreateProjectForm);
