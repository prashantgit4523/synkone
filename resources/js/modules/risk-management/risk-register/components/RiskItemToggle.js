import React, { useContext } from "react";
import { Link, usePage } from "@inertiajs/inertia-react";
import {
    AccordionContext,
    useAccordionButton,
    Dropdown,
} from "react-bootstrap";

const RiskItemToggle = ({ eventKey, callback, risk, onDelete, position, showRiskAddView, clickable }) => {
    const { request_url } = usePage().props;
    const currentEventKey = useContext(AccordionContext);
    const handleOnClick = useAccordionButton(
        eventKey,
        () => callback && callback(eventKey)
    );

    const isOnRiskPage = () => {
        if (request_url.includes('dashboard')) {
            return false;
        }
        else {
            return true;
        }
    }

    return (
        <tr className="risk-table">
            {
            clickable ? (
                <td onClick={handleOnClick} style={{ width: "10%",cursor:'pointer' }}>
                    <span className="icon-sec me-2 expandable-icon-wp">
                        <a
                            className="link-primary risk-single-list"
                            aria-expanded="false"
                        >
                            <i
                                className={
                                    currentEventKey.activeEventKey === eventKey
                                        ? "icon fas fa-chevron-down me-2 expand-icon-w"
                                        : "icon fas fa-chevron-right me-2 expand-icon-w"
                                }
                            />
                            {position}
                        </a>
                    </span>
                </td>
            ) : (
                <td style={{ width: "10%",cursor:'pointer' }}>
                    <span className="icon-sec me-2 expandable-icon-wp disabled_click">
                        <a
                            className="link-primary risk-single-list"
                            aria-expanded="false"
                        >
                            <i data-tip="Change to current date to interact with the dashboard"
                                className={
                                    currentEventKey.activeEventKey === eventKey
                                        ? "icon fas fa-chevron-down me-2 expand-icon-w"
                                        : "icon fas fa-chevron-right me-2 expand-icon-w"
                                }
                            />
                            {position}
                        </a>
                    </span>
                </td>
            )}
            <td style={{ width: "30%" }}>
                {isOnRiskPage() ?
                    (
                        clickable
                            ? <span style={{ color: '#6658dd', textDecoration: 'none', cursor: 'pointer' }}
                                onClick={() => showRiskAddView(true, risk)} >
                                {decodeHTMLEntity(risk.name)}
                            </span>
                            : <span style={{ color: '#6658dd', textDecoration: 'none', cursor: 'not-allowed' }}
                                data-tip="Change to current date to interact with the dashboard" >
                                {decodeHTMLEntity(risk.name)}
                            </span>
                    )
                    :
                    (
                        clickable
                            ? <Link preserveScroll
                                href={
                                    `${appBaseURL}/risks/projects/` +
                                    risk.project_id +
                                    `/show?risk=` + risk.id
                                }
                            >
                                {decodeHTMLEntity(risk.name)}
                            </Link>
                            : <Link preserveScroll
                                data-tip="Change to current date to interact with the dashboard"
                                className="disabled_click"
                                onClick={(event) => event.preventDefault()}>
                                {decodeHTMLEntity(risk.name)}
                            </Link>
                    )
                }

            </td>
            <td style={{ width: "30%" }}>
                <span
                >
                    {decodeHTMLEntity(risk.project.department_title)}
                </span>
            </td>
            <td style={{ width: "30%" }}>
                <span
                >
                    {decodeHTMLEntity(risk.project.name)}
                </span>
            </td>
            <td style={{ width: "30%" }}>
                <span
                >
                    {decodeHTMLEntity(risk.category.name)}
                </span>
            </td>
            <td style={{ width: "5%" }} className="hide-on-xs hide-on-sm">
                {risk.mapped_controls.length > 0 ? (
                    clickable ? (
                        <Link
                            href={
                                `${appBaseURL}/compliance/projects/` +
                                risk.mapped_controls[0].project_id +
                                `/controls/` +
                                risk.mapped_controls[0].id +
                                `/show/`
                            }
                        >
                            {" "}
                            {decodeHTMLEntity(risk.mapped_controls[0].controlId)}
                        </Link>
                    ) : (
                        <Link className="disabled_click" data-tip="Change to current date to interact with the dashboard">
                            {" "}
                            {decodeHTMLEntity(risk.mapped_controls[0].controlId)}
                        </Link>
                    )
                ) : (
                    "None"
                )}
            </td>
            <td
                style={{ width: "5%" }}
                className="hide-on-xs status-td"
            >
                <span className={risk.status == "Open" ? "badge bg-danger rounded-pill" : "badge bg-success rounded-pill"}>
                    {risk.status === "Close" ? "Closed" : risk.status}
                </span>
            </td>
            <td
                style={{ width: "10%" }}
                className="hide-on-xs treatment-option-td"
            >
                {risk.treatment_options}
            </td>
            <td
                style={{ width: "5%" }}
                className="hide-on-xs inherent-likelihood-td"
            >
                {risk.likelihood}
            </td>
            <td
                style={{ width: "5%" }}
                className="hide-on-xs hide-on-sm inherent-impact-td"
            >
                {risk.impact}
            </td>
            <td
                style={{ width: "12%" }}
                className="hide-on-xs hide-on-sm inherent-score-td"
            >
                {risk.inherent_score}
            </td>
            <td
                style={{ width: "12%" }}
                className="hide-on-xs hide-on-sm residual-score-td"
            >
                {risk.residual_score}
            </td>
            <td>
                {
                    clickable ? (
                        <Dropdown className="btn-group">
                            <Dropdown.Toggle
                                variant="secondary"
                                className="table-action-btn arrow-none btn btn-light btn-sm"
                                aria-expanded="false"
                            >
                                <i className="mdi mdi-dots-horizontal" />
                            </Dropdown.Toggle>
                            <Dropdown.Menu className="dropdown-menu-end ">
                                <Dropdown.Item
                                    onClick={() => onDelete(risk.id)}
                                >
                                    <i className="mdi mdi-delete-forever me-2 text-muted font-18 vertical-middle" />
                                    Delete
                                </Dropdown.Item>
                            </Dropdown.Menu>
                        </Dropdown>
                    ) : (
                        <Dropdown className="btn-group">
                            <Dropdown.Toggle
                                data-tip="Change to current date to interact with the dashboard"
                                variant="secondary"
                                className="table-action-btn arrow-none btn btn-light btn-sm disabled_click"
                                aria-expanded="false"
                            >
                                <i className="mdi mdi-dots-horizontal" />
                            </Dropdown.Toggle>
                        </Dropdown>
                    )
                }
            </td>
        </tr>
    );
};

export default RiskItemToggle;
