import { Inertia } from "@inertiajs/inertia";
import React from "react";
import { useForm } from "react-hook-form";
import AuthLayout from "../../layouts/auth-layout/AuthLayout";
import Logo from "../../layouts/auth-layout/components/Logo";
import { Link } from "@inertiajs/inertia-react";
import FlashMessages from '../../common/FlashMessages'

export default function Activate(props) {
    document.title = "License Activation";
    const logBtn = true;
    const propsData = { props };
    const apiErrors = propsData.props.errors ? propsData.props.errors : null;

    const { register, formState: { errors }, handleSubmit, getValues, reset } = useForm({
        mode: 'onSubmit',
    });

    const submit = () => {
        const formData = getValues();
        Inertia.post(route('license.activate'), formData, {
            onError: () => {
                reset({
                    ...getValues(),
                    password: ''
                })
            }
        });
    };

    return (
        <AuthLayout>
            <div className="row justify-content-center">
                <div className="col-md-8 col-lg-6 col-xl-5">
                    <div className="login__main card bg-pattern">
                        <div className="card-body p-4">
                            {/* <!-- LOGO DISPLAY NAME -->*/}
                            <Logo></Logo>

                            <FlashMessages></FlashMessages>

                            <form
                                onSubmit={handleSubmit(submit)}
                                className="absolute-error-form d-block"
                                id="login-form"
                            >
                                <div className="position-relative mb-3">
                                    <label htmlFor="license">License Code <span className="text-danger">*</span></label>
                                    <input
                                        type="text"
                                        {...register("license", {
                                            required: true,
                                            maxLength: 190,
                                        })}
                                        className={`form-control ${errors.license && "border-error"}`}
                                        name="license"
                                        id="license"
                                        placeholder="Enter your purchase/license code"
                                        autoComplete="off"
                                        autoFocus={apiErrors.license ? true : false}
                                    />
                                    <span className="error-msg msg">
                                        {errors.license &&
                                            errors.license.type ===
                                            "required" && (
                                                <div className="invalid-feedback d-block">
                                                    The license name is required
                                                </div>
                                            )}
                                    </span>
                                </div>
                                <div className="position-relative mb-3">
                                    <label htmlFor="client">Client Name <span className="text-danger">*</span></label>
                                    <input
                                        type="text"
                                        {...register("client", {
                                            required: true,
                                            maxLength: 190,
                                        })}
                                        className={`form-control ${errors.client && "border-error"}`}
                                        name="client"
                                        id="client"
                                        placeholder="Enter your client name"
                                        autoComplete="off"
                                        autoFocus={apiErrors.client ? true : false}
                                    />

                                    <span className="error-msg msg">
                                        {errors.client &&
                                            errors.client.type ===
                                            "required" && (
                                                <div className="invalid-feedback d-block">
                                                    The client name field is required
                                                </div>
                                            )}
                                    </span>
                                </div>

                                <div className="position-relative mb-0 text-center">
                                    <button
                                        id="login-btn"
                                        className="btn btn-primary w-100 secondary-bg-color"
                                    >
                                        Activate
                                    </button>
                                </div>

                                {logBtn &&
                                    <>
                                        <p className="text-center p-2 mb-0"><strong>OR</strong></p>
                                        <div className="position-relative mb-0 text-center">
                                            <Link
                                                href={route('homepage')}
                                                id="login-btn"
                                                className="btn btn-primary w-100 secondary-bg-color"
                                            >
                                                Already activated? Log In
                                            </Link>
                                        </div>
                                    </>
                                }
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
