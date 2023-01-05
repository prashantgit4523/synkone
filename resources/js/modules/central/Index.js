import AuthLayout from "../../layouts/auth-layout/AuthLayout";
import { Link } from "@inertiajs/inertia-react";
import Logo from "../../layouts/auth-layout/components/Logo";

export default function Index() {
    return (
        <AuthLayout>
            <div className="row justify-content-center">
                <div className="col-md-8 col-lg-6 col-xl-5">
                    <div className="login__main card bg-pattern">
                        <div className="card-body p-4">
                            {/* <!-- LOGO DISPLAY NAME -->*/}
                            <Logo></Logo>

                            <div className="row align-items-center position-relative mb-0 text-center">
                                <Link
                                    id="login-btn"
                                    href={route('register.domain.show_form')}
                                    className="btn btn-primary d-grid secondary-bg-color"
                                    type="submit"
                                >
                                    Register Domain
                                </Link>
                            </div>
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
