import React, { useEffect } from "react";
import AuthLayout from "../../layouts/auth-layout/AuthLayout";
import { Link } from "@inertiajs/inertia-react";
import Logo from "../../layouts/auth-layout/components/Logo";
import './SSOLogin.css';
import MicrosoftIcon from "../../../../public/assets/images/sso-icons/microsoft.png";
import GoogleIcon from "../../../../public/assets/images/sso-icons/google.png";
import OktaIcon from "../../../../public/assets/images/sso-icons/okta.png";
import ShieldIcon from "../../../../public/assets/images/sso-icons/shield.png";
import { usePage } from '@inertiajs/inertia-react';
import FlashMessages from "../../common/FlashMessages";
import route from "ziggy-js";

export default function LoginPage(props) {
    document.title = "Login";

    const { microsoftSSO, googleSSO, oktaSSO, isSSOConfigured } = usePage().props;

    return (
        <AuthLayout>
            <div className="row justify-content-center">
                <div className="col-md-8 col-lg-6 col-xl-4">
                    <div className="login__main card bg-pattern">
                        <div className="card-body p-4">
                            {/* <!-- LOGO DISPLAY NAME -->*/}
                            <Logo></Logo>

                            <div className="position-relative mb-0 text-center">
                                {microsoftSSO && <a href='/auth/sso/microsoft' className="btn btn-sso-button w-100 mt-2">
                                    <img src={MicrosoftIcon} alt="microsoft" className="sso-icon"/>
                                    {"  "}Sign in with Microsoft{" "}
                                </a>}

                                {googleSSO && <a href='/auth/sso/google' className="btn btn-sso-button w-100 mt-2">
                                    <img src={GoogleIcon} alt="google" className="sso-icon"/>
                                    {"  "}Sign in with Google{" "}
                                </a>}

                                {oktaSSO && <a href='/auth/sso/okta' className="btn btn-sso-button w-100 mt-2">
                                    <img src={OktaIcon} alt="okta" className="sso-icon" style={{width: 70}}/>
                                    {"  "}Sign in with Okta{" "}
                                </a>}

                                {isSSOConfigured && <a href={route("saml2.login")} className="btn btn-sso-button w-100 mt-2">
                                    <img src={ShieldIcon} alt="sso" className="sso-icon"/>
                                    {"  "}Sign in with SSO{" "}
                                </a>}
                            </div>

                            <div className="col-12 text-center mt-2">
                                <span className="mb-2">Signing in for the first time?</span>
                            <p>
                                {" "}
                                <Link
                                    href={route('manual-login')}
                                    className="ms-1"
                                    style={{fontSize: '17px', color:'grey'}}
                                >
                                    Sign in with email
                                </Link>
                            </p>
                        </div>
                        </div>
                        {/* <!-- end card-body --> */}
                    </div>
                    {/* <!-- end card --> */}

                    {/* <!-- end row --> */}
                </div>
                {/* <!-- end col --> */}
            </div>
            {/* <!-- end row --> */}
        </AuthLayout>
    );
}
