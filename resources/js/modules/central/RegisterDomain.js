import { Inertia } from "@inertiajs/inertia";
import React from "react";
import { useForm } from "react-hook-form";
import AuthLayout from "../../layouts/auth-layout/AuthLayout";
import Logo from "../../layouts/auth-layout/components/Logo";

export default function RegisterDomain(props) {
    document.title = "Register Domain";
    const { url } = props;
    const propsData = { props };
    const apiErrors = propsData.props.errors ? propsData.props.errors : null;
    const domain = propsData.props.flash?.domain

    const { register, formState: { errors }, handleSubmit, getValues, reset } = useForm({
        mode: 'onSubmit',
    });

    const submit = () => {
        const formData = getValues();
        Inertia.post(route('register.domain.create'), formData, {
            onError: () => {
                reset({
                    ...getValues(),
                    password: ''
                })
            },
            onSuccess: () => {
                reset();
            }
        });
    };

    var todayDate = new Date();
    var numberOfDaysToAdd = 10;
    var result = new Date(todayDate.setDate(todayDate.getDate() + numberOfDaysToAdd));
    var today = result.toISOString().split('T')[0];

    var today2 = new Date();
    var result2 = new Date(today2.setFullYear(today2.getFullYear() + 1));
    var aYearFromNow = result2.toISOString().split('T')[0];

    const renderAlertMsg = () => {
        return (domain &&
                <p className="alert alert-success">
                    Successfully registered domain. Please checkout <a href={"http://" + domain} target="_blank">{domain}</a>.
                </p>
        )
    }

    const renderCompanyNameFieldErrorMsg = () => {
        return (
            <span className="error-msg msg">
                {errors.company &&
                    errors.company.type ===
                    "required" && (
                        <div className="invalid-feedback d-block">
                            The company name is required
                        </div>
                    )}
                {errors.company &&
                    errors.company.type === "pattern" && (
                        <div className="invalid-feedback d-block">
                            Please enter a valid company name
                        </div>
                    )}
            </span>
        )
    }

    const renderNameFieldErrorMsg = () => {
        return (
            <span className="error-msg msg">
                {errors.name &&
                    errors.name.type ===
                    "required" && (
                        <div className="invalid-feedback d-block">
                            The fullname is required
                        </div>
                    )}
                {errors.name &&
                    errors.name.type === "pattern" && (
                        <div className="invalid-feedback d-block">
                            Please enter a valid name
                        </div>
                    )}
            </span>
        )
    }

    const renderDomainFieldErrorMsg = () => {
        return (
            <span className="error-msg msg" style={{ position: 'relative' }}>
                {errors.domain &&
                    errors.domain.type ===
                    "required" && (
                        <div className="invalid-feedback d-block">
                            The domain field is required
                        </div>
                    )}
                {errors.domain &&
                    errors.domain.type === "pattern" && (
                        <div className="invalid-feedback d-block">
                            Please enter a valid domain
                        </div>
                    )}

                {
                    apiErrors.domain && (
                        <div className="invalid-feedback d-block">{apiErrors.domain}</div>
                    )
                }
            </span>
        )
    }

    const renderEmailFieldErrorMsg = () => {
        return (
            <span className="error-msg msg">
                {errors.email &&
                    errors.email.type ===
                    "required" && (
                        <div className="invalid-feedback d-block">
                            The email address is
                            required
                        </div>
                    )}
                {errors.email &&
                    errors.email.type === "pattern" && (
                        <div className="invalid-feedback d-block">
                            Please enter a valid email
                            address
                        </div>
                    )}
                {apiErrors.email && (
                    <div className="invalid-feedback d-block">
                        {apiErrors.email}
                    </div>
                )}
            </span>
        )
    }

    const passwordFieldErrorMsg = () => {
        return (
            <span className="error-msg msg">
                {errors.password &&
                    errors.password.type ===
                    "required" && (
                        <div className="invalid-feedback d-block">
                            The password field is required
                        </div>
                    )}
                {apiErrors.password && (
                    <div className="invalid-feedback d-block">
                        {apiErrors.password}
                    </div>
                )}
            </span>
        )
    }

    const expiryDateFieldErrorMsg = () => {
        return (
            <span className="error-msg msg">
                {apiErrors.subscription_expiry_date && (
                    <div className="invalid-feedback d-block">
                        {apiErrors.subscription_expiry_date}
                    </div>
                )}
            </span>
        )
    }

    return (
        <AuthLayout>
           {renderAlertMsg()}

            <div className="row justify-content-center">
                <div className="col-md-8 col-lg-6 col-xl-5">
                    <div className="login__main card bg-pattern">
                        <div className="card-body p-4">
                            {/* <!-- LOGO DISPLAY NAME -->*/}
                            <Logo></Logo>

                            <form
                                onSubmit={handleSubmit(submit)}
                                className="absolute-error-form d-block"
                                id="login-form"
                            >
                                <div className="position-relative mb-3">
                                    <label htmlFor="company">Company <span className="text-danger">*</span></label>
                                    <input
                                        type="text"
                                        {...register("company", {
                                            required: true,
                                            maxLength: 255,
                                        })}
                                        className={`form-control ${errors.company && "border-error"}`}
                                        name="company"
                                        id="company"
                                        placeholder="Enter your company name"
                                        autoComplete="off"
                                        autoFocus={apiErrors.company ? true : false}
                                    />
                                    {renderCompanyNameFieldErrorMsg()}
                                </div>
                                <div className="position-relative mb-3">
                                    <label htmlFor="name">Full Name <span className="text-danger">*</span></label>
                                    <input
                                        type="text"
                                        {...register("name", {
                                            required: true,
                                            maxLength: 255,
                                        })}
                                        className={`form-control ${errors.name && "border-error"}`}
                                        name="name"
                                        id="name"
                                        placeholder="Enter your full name"
                                        autoComplete="off"
                                        autoFocus={apiErrors.name ? true : false}
                                    />

                                    {renderNameFieldErrorMsg()}
                                </div>

                                <label htmlFor="name">Domain <span className="text-danger">*</span></label>
                                <div className="position-relative mb-3">
                                    <input
                                        type="text"
                                        {...register("domain", {
                                            required: true,
                                            maxLength: 255,
                                            pattern: /^[a-zA-Z0-9_.-]*$/,
                                        })}
                                        style={{ width: '60%', display: 'inline-flex' }}
                                        className={`form-control ${errors.domain && "border-error"}`}
                                        name="domain"
                                        id="domain"
                                        placeholder="Enter your domain"
                                        autoComplete="off"
                                        autoFocus={apiErrors.domain ? true : false}
                                    />
                                    <span
                                        className="flex text-sm form-control bg-grey"
                                        style={{ width: '39%', display: 'inline-flex', background: 'gainsboro' }}
                                    >
                                        <span>.{url}</span>
                                    </span>
                                    {renderDomainFieldErrorMsg()}
                                </div>

                                <div
                                    id="email-group"
                                    className="position-relative mb-3"
                                >
                                    <label
                                        className="form-label"
                                        htmlFor="email"
                                    >
                                        Email address{" "}
                                        <span className="text-danger">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        {...register("email", {
                                            required: true,
                                            maxLength: 190,
                                            pattern:
                                                /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/,
                                        })}
                                        className={`form-control ${errors.email && "border-error"}`}
                                        name="email"
                                        id="emailaddress"
                                        placeholder="Enter your email"
                                        autoComplete="off"
                                        autoFocus={apiErrors.email ? true : false}
                                    />
                                    {renderEmailFieldErrorMsg()}
                                </div>

                                <div
                                    id="password-group"
                                    className="position-relative mb-3"
                                >
                                    <label
                                        className="form-label"
                                        htmlFor="password"
                                    >
                                        Password{" "}
                                        <span className="text-danger">*</span>
                                    </label>
                                    <input
                                        {...register("password", {
                                            required: true,
                                            maxLength: 190,
                                        })}
                                        className={`form-control ${errors.password && "border-error"}`}
                                        name="password"
                                        type="password"
                                        autoComplete="new-password"
                                        id="password"
                                        placeholder="Enter your password"
                                        defaultValue={errors && ''} />
                                    {passwordFieldErrorMsg()}
                                </div>

                                <div className="position-relative mb-3">
                                    <label
                                        className="form-label"
                                        htmlFor="subscription_expiry_date"
                                    >
                                        Subscription expiry date
                                        <span className="text-danger">*</span>
                                    </label>
                                    <input
                                        type="date"
                                        {...register("subscription_expiry_date")}
                                        className={`form-control ${errors.subscription_expiry_date && "border-error"}`}
                                        name="subscription_expiry_date"
                                        min={today}
                                        defaultValue={aYearFromNow}
                                        id="expiry_date"
                                        placeholder=""
                                    />
                                    {expiryDateFieldErrorMsg()}
                                </div>

                                <div className="position-relative mb-0 text-center">
                                    <button
                                        id="login-btn"
                                        className="btn btn-primary w-100 secondary-bg-color"
                                    >
                                        Register
                                    </button>
                                </div>
                            </form>
                        </div>
                        {/* <!-- end card-body --> */}
                    </div>
                    {/* <!-- end card --> */}
                </div>
                {/* <!-- end col --> */}
            </div>
            {/* <!-- end row --> */}
        </AuthLayout>
    );
}
