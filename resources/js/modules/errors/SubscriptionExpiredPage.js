import AuthLayout from "../../layouts/auth-layout/AuthLayout";
import Logo from "../../layouts/auth-layout/components/Logo";

function SubscriptionExpiredPage({expiry_date}) {
    return ( 
        <AuthLayout>
            <div className="row justify-content-center">
                <div className="col-md-8 col-lg-6 col-xl-4">
                    <div className="login__main card bg-pattern">
                        <div className="card-body p-4">
                            {/* <!-- LOGO DISPLAY NAME -->*/}
                            <Logo></Logo>

                            <div className="text-center m-auto">
                                <h4 className="text-dark-50 text-center mt-3">
                                    Subscription Expired !
                                </h4>
                                <p className="text-muted mb-4">
                                        Your license expired on {expiry_date}, contact CyberArrow on <a className="secondary-text-color" href="mailto:info@cyberarrow.io">info@cyberarrow.io</a>  to renew.
                                    <br />
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthLayout>
     );
}

export default SubscriptionExpiredPage;