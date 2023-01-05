import {Inertia} from "@inertiajs/inertia";

import '../responsive.css';
import {OverlayTrigger, Tooltip} from "react-bootstrap";

function CompanyInfo(props) {
    const {company, setSelectedProvider, setModalShown, disconnectDisabled} = props;

    const disconnectService = (id) => {
        AlertBox(
            {
                title: "Are you sure ?",
                text: "This will affect existing projects and current evidence will become non-implemented and manual evidence need to be given.",
                showCancelButton: true,
                confirmButtonColor: "#ff0000",
                confirmButtonText: "Yes, disconnect it!",
                icon: 'warning',
                iconColor: '#ff0000'
            },
            function (res) {
                if (res.isConfirmed) {
                    Inertia.post(route('integrations.disconnect'), {
                            id: id
                        }, {
                            preserveState: false,
                            onSuccess: page => {
                                console.log('page => ', company);
                            },
                        }
                    );
                }
            }
        );
    }

    const redirectToProvider = () => {
        if (company.provider.protocol === 'oauth') {
            window.location.href = "/auth/" + company.slug + "/redirect";
            return;
        }
        setSelectedProvider(company.provider);
        setModalShown(true);
    }

    function renderDisabledConnectButton(showTooltip = false, message = "") {
        if(!showTooltip) {
            return (
                <a
                    className="btn btn-link disabled btn-sm width-sm"
                    href="#"
                >
                    Connect
                </a>
            );
        }
        return (
            <OverlayTrigger
                placement="bottom"
                delay={{ show: 250, hide: 400 }}
                overlay={(props) => (
                    <Tooltip id="button-tooltip" {...props}>
                        {message}
                    </Tooltip>
                )}
            >
                <span className="d-inline-block">
                    <a
                        className="btn btn-link disabled btn-sm width-sm"
                        style={{pointerEvents: 'none'}}
                        href="#"
                    >
                        Connect
                    </a>
                </span>
            </OverlayTrigger>
        );
    }

    const onePerCategoryEnabled = !company.implemented_integration && company.ready;
    const message = `Another ${company.category.short_name} provider already connected`;

    return (
        <div className="card bg-pattern h-100">
            <div className="card-body text-center">
                {onePerCategoryEnabled  && <div className="badge bg-dark badge-top-right coming-soon-badge">{message}</div>}
                {(!company.implemented_integration && !company.ready) && <div className="badge bg-soft-info text-info badge-top-right coming-soon-badge">Coming soon</div>}
                <div className="mt-3">
                    <img src={company.logo_link} alt="" className="avatar-xl mb-3"/>
                    <h4 className="mb-1 font-20 clamp clamp-1">{company.name}</h4>
                </div>

                <p className="font-14 text-muted clamp clamp-3">{company.description}</p>

                <div className="text-center cursor-not-allowed">
                    {
                        !company.implemented_integration ? renderDisabledConnectButton(onePerCategoryEnabled, message) : (company.provider.current_scopes_count > company.provider.previous_scopes_count ? <a
                            className="btn btn-primary btn-sm width-sm"
                            onClick={redirectToProvider}
                        >
                            Re-Connect
                        </a> : (company.connected ? <button
                            className="btn btn-primary btn-sm width-sm"
                            disabled={disconnectDisabled}
                            onClick={() => disconnectService(company.id)}
                        >
                            Disconnect
                        </button> : <a
                            className="btn btn-primary btn-sm width-sm"
                            onClick={() => redirectToProvider(company)}
                        >
                            Connect
                        </a>))
                    }

                </div>
            </div>
        </div>
    )
}

export default CompanyInfo;
