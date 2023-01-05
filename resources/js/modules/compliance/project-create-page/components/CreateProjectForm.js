import React, {
    Fragment,
    useEffect,
    forwardRef,
    useImperativeHandle,
    useState
} from "react";
import { useForm, Controller } from "react-hook-form";
import { usePage } from "@inertiajs/inertia-react";
import { Inertia } from "@inertiajs/inertia";
import { useStateIfMounted } from "use-state-if-mounted";
import { useSelector, useDispatch } from "react-redux";
import Select from "../../../../common/custom-react-select/CustomReactSelect";
import axios from 'axios';
import ReactTooltip from 'react-tooltip';


function CreateProjectForm(props, ref) {
    const { project, setFormSubmitting, updateWizardCurrentStep } = props;
    const [standards, setStandards] = useStateIfMounted([]);
    const [projects, setProjects] = useStateIfMounted([]);
    const { assignedControls } = usePage().props;
    const dispatch = useDispatch();
    const { register, reset, formState: { errors }, control, trigger, getValues } = useForm({
        mode: "onChange",
        // reValidateMode: 'onChange'
    });
    const appDataScope = useSelector(
        (state) => state.appDataScope.selectedDataScope.value
    );
    const selectableStandardOptions = standards.map(
        (standard) => ({ value: standard.id, label: standard.name })
    );
    const [selected, setSelected] = useState();
    
    useEffect(async () => {
        let httpResponse = await axiosFetch.get(
            "administration/compliance-template/list"
        );
        let resData = httpResponse.data;

        if (resData.success) {
            let data = resData.data;

            /* SETTING THE STANDARDS*/
            setStandards(data);
        }
    }, []);

    /* Setting the form field value on load*/
    useEffect(() => {
        reset({
            name: project.name,
            description: project.description,
            standard_id: project.standard_id,
        });
    }, [project]);

    let cancelToken;

    /* Custom form validation rule*/
    const nameIsUnique = async (name) => {
        if (typeof cancelToken != typeof undefined) {
            cancelToken.cancel("Operation canceled.")
        }

        cancelToken = axios.CancelToken.source()

        try {
            const results = await axiosFetch.get(
                route("compliance.projects.check-project-name-taken", project.id),
                { cancelToken: cancelToken.token, params: {name} }
            );

            return results.data;
        } catch (error) {
            console.log(error);
        }
    };

    /* checking from validity*/
    const isFormValid = async () => {
        let requiredForCreateProject = ["name", "description", "standard_id"]
        let requiredForUpdateProject = ["name", "description",]
        return await trigger(project.id ? requiredForUpdateProject : requiredForCreateProject);
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
            ? route("compliance-projects-update", project.id)
            : route("compliance-projects-store");

        /* Setting form submitting loader */
        setFormSubmitting(true);

        data["data_scope"] = appDataScope;
        Inertia.post(submitURL, data, {
            onError: (page) => {
                updateWizardCurrentStep(1);
            },
            onFinish: () => {
                dispatch({ type: "reportGenerateLoader/hide" });
            }
        });
    };

    const handleFormSubmit = async () => {
        if (!project.id) {
            dispatch({ type: "reportGenerateLoader/show", payload: "Your project is being created, it's almost ready..." });
        }
        const formData = getValues();
        if(formData.project_to_map == ''){
            formData.project_to_map = undefined
        }

        /* Submitting form data */
        onSubmit(formData);
    };

    /* Triggers on standard update */
    const handleStandardChange = (val) => {
        reset({
            ...getValues(),
            standard_id: val.value,
            project_to_map: undefined
        });
        getProjectOptions(val.value);
        setSelected("")
    };
    const handleProjectToMap = (val) => {
        reset({
            ...getValues(),
            project_to_map: val.value,
        });
        val.label == 'None' ? setSelected("") : setSelected({ value: val.value, label: val.label})
    };
    

    const getProjectOptions = async (val) => {
        let httpResponse = await axiosFetch.get(
            route("compliance.projects.data-for-options"),{
                params: {
                    standard_id: val,
                },
            }
        );
        let data = httpResponse.data;

        /* SETTING THE PROJECT OTPIONS*/
        let project_options=data.map(
            (project) => ({ value: project.id, label: project.name })
        );
        project_options.unshift({ value: '', label: 'None'})
        setProjects(project_options);
    }

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
                <div className="row mb-3">
                    <label
                        className="col-md-3 form-label col-form-label"
                        htmlFor="standard"
                    >
                        Standard <span className="required text-danger">*</span>
                    </label>
                    <div className="col-md-9">
                        <Controller
                            control={control}
                            name="standard_id"
                            rules={{ required: true }}
                            render={({ field: { onChange } }) => (
                                <Select
                                    placeholder="Choose Standard"
                                    className="react-select"
                                    classNamePrefix="react-select"
                                    defaultValue={project.id > 0 ? { label: project.standard, value: project.standard_id } : false}
                                    onChange={(val) => { onChange(val); handleStandardChange(val) }}
                                    options={selectableStandardOptions}
                                    tabIndex={3}
                                    isDisabled={project.id}
                                />
                            )}
                        />
                        {errors.standard_id &&
                            errors.standard_id.type === "required" && (
                                <div className="invalid-feedback d-block">
                                    The Standard field is required.
                                </div>
                            )}
                    </div>
                </div>
                { projects.length > 1 && !project.id  &&
                    <div className="row mb-3">
                        <label
                            className="col-md-3 form-label col-form-label"
                            htmlFor="standard"
                        >
                        <ReactTooltip place="right" effect="solid" multiline={true} />
                        Map to project <i className="mdi mdi-help-circle help-button-project" data-tip="Use this feature to auto-map previously implemented manual controls from another project to this new project.<br /> It’s optional, if you use this feature you will not have to do any control mapping across standards for manual controls."></i>
                        </label>
                        <div className="col-md-9">
                            <Controller
                                control={control}
                                name="project_to_map"
                                rules={{ required: true }}
                                render={({ field: { onChange } }) => (
                                    <Select
                                        placeholder="Choose existing project"
                                        className="react-select"
                                        classNamePrefix="react-select"
                                        defaultValue={project.id > 0 ? { label: project.standard, value: project.standard_id } : false}
                                        onChange={(val) => { onChange(val); handleProjectToMap(val) }}
                                        options={projects}
                                        tabIndex={3}
                                        isDisabled={project.id}
                                        value={selected}
                                    />
                                )}
                            />
                        </div>
                    </div>
                }
                
            </form>
        </Fragment>
    );
}

export default forwardRef(CreateProjectForm);
